<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Condition\ConditionState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseConditionRecord;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseConditionCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseConditionStatusChanged;

class PremiseConditionService
{
    public function create(Property $premise, array $attributes): PremiseConditionRecord
    {
        $status = $attributes['status'] ?? 'draft';
        if (!ConditionState::canCreate($status)) {
            throw ValidationException::withMessages(['status' => 'Condition records must begin as draft, in progress, or completed.']);
        }
        $record = PremiseConditionRecord::create(array_merge($attributes, [
            'company_id' => $premise->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $premise->id,
            'recorded_at' => $attributes['recorded_at'] ?? now(),
            'completed_at' => $status === 'completed' ? ($attributes['completed_at'] ?? now()) : null,
            'status' => $status,
        ]));
        event(new PremiseConditionCreated($record));
        return $record;
    }

    public function transition(PremiseConditionRecord $record, string $toStatus, array $attributes = []): PremiseConditionRecord
    {
        return DB::transaction(function () use ($record, $toStatus, $attributes) {
            $record = PremiseConditionRecord::withoutGlobalScopes()
                ->where('company_id', $record->company_id)->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $previous = $record->status;
            if (!ConditionState::canTransition($previous, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Condition record cannot move from {$previous} to {$toStatus}. "]);
            }

            $updates = ['status' => $toStatus];
            foreach (['scheduled_for', 'recorded_at', 'score', 'summary', 'findings', 'actions', 'evidence_document_ids', 'external_reference', 'metadata'] as $field) {
                if (array_key_exists($field, $attributes)) $updates[$field] = $attributes[$field];
            }
            if ($toStatus === 'completed') $updates['completed_at'] = $attributes['completed_at'] ?? now();
            if ($toStatus === 'approved') $updates['approved_at'] = $attributes['approved_at'] ?? now();
            $record->update($updates);
            event(new PremiseConditionStatusChanged($record, $previous));
            return $record->fresh(['space', 'occupancy', 'partyRole']);
        });
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
