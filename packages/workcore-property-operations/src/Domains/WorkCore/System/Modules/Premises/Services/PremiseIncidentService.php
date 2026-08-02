<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Incidents\IncidentState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseIncident;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseIncidentCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseIncidentStatusChanged;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseIncidentWorkLinked;

class PremiseIncidentService
{
    public function create(Property $premise, array $attributes): PremiseIncident
    {
        $status = $attributes['status'] ?? 'open';
        if (!IncidentState::canCreate($status)) {
            throw ValidationException::withMessages(['status' => 'Incidents must begin as open or triaged.']);
        }
        $incident = PremiseIncident::create(array_merge($attributes, [
            'company_id' => $premise->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $premise->id,
            'reported_at' => $attributes['reported_at'] ?? now(),
            'status' => $status,
        ]));
        event(new PremiseIncidentCreated($incident));
        return $incident;
    }

    public function transition(PremiseIncident $incident, string $toStatus, array $attributes = []): PremiseIncident
    {
        return DB::transaction(function () use ($incident, $toStatus, $attributes) {
            $incident = $this->locked($incident);
            $previous = $incident->status;
            if (!IncidentState::canTransition($previous, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Incident cannot move from {$previous} to {$toStatus}. "]);
            }
            $updates = ['status' => $toStatus];
            foreach (['severity', 'privacy_level', 'description', 'metadata'] as $field) {
                if (array_key_exists($field, $attributes)) $updates[$field] = $attributes[$field];
            }
            if ($toStatus === 'resolved') $updates['resolved_at'] = $attributes['resolved_at'] ?? now();
            $incident->update($updates);
            event(new PremiseIncidentStatusChanged($incident, $previous));
            return $incident->fresh(['space', 'occupancy', 'reportedByPartyRole']);
        });
    }

    public function linkWorkCore(PremiseIncident $incident, array $attributes): PremiseIncident
    {
        return DB::transaction(function () use ($incident, $attributes) {
            $incident = $this->locked($incident);
            if (IncidentState::isTerminal($incident->status)) {
                throw ValidationException::withMessages(['incident' => 'Closed or cancelled incidents cannot be linked to new work.']);
            }
            $previous = $incident->status;
            $updates = [
                'workcore_record_type' => $attributes['workcore_record_type'],
                'workcore_record_id' => $attributes['workcore_record_id'] ?? null,
                'workcore_reference' => $attributes['workcore_reference'] ?? null,
                'workcore_status' => $attributes['workcore_status'] ?? 'linked',
                'workcore_linked_at' => now(),
            ];
            if ($incident->status !== 'work_requested' && IncidentState::canTransition($incident->status, 'work_requested')) {
                $updates['status'] = 'work_requested';
            }
            $incident->update($updates);
            event(new PremiseIncidentStatusChanged($incident, $previous));
            event(new PremiseIncidentWorkLinked($incident));
            return $incident->fresh();
        });
    }

    private function locked(PremiseIncident $incident): PremiseIncident
    {
        return PremiseIncident::withoutGlobalScopes()
            ->where('company_id', $incident->company_id)->whereKey($incident->id)->lockForUpdate()->firstOrFail();
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
