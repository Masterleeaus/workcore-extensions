<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Requests\ServiceRequestState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalGrant;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSelfServiceRequest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseSelfServiceRequestCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseSelfServiceRequestWorkLinked;

class PremiseSelfServiceRequestService
{
    public function submit(PremisePortalGrant $grant, array $attributes): PremiseSelfServiceRequest
    {
        $request = PremiseSelfServiceRequest::create([
            'company_id' => $grant->company_id, 'premise_id' => $grant->premise_id,
            'space_id' => $grant->occupancy?->space_id, 'portal_grant_id' => $grant->id,
            'party_role_id' => $grant->party_role_id, 'occupancy_id' => $grant->occupancy_id,
            'request_number' => 'REQ-' . now()->format('ymd') . '-' . Str::upper(Str::random(9)),
            'request_type' => $attributes['request_type'], 'priority' => $attributes['priority'] ?? 'normal',
            'privacy_level' => $attributes['privacy_level'] ?? 'standard', 'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null, 'status' => 'submitted', 'submitted_at' => now(),
            'metadata' => $attributes['metadata'] ?? [],
        ]);
        event(new PremiseSelfServiceRequestCreated($request));
        return $request;
    }

    public function transition(PremiseSelfServiceRequest $request, string $toStatus, array $attributes = []): PremiseSelfServiceRequest
    {
        return DB::transaction(function () use ($request, $toStatus, $attributes): PremiseSelfServiceRequest {
            $request = $this->locked($request);
            $from = $request->status;
            if (!ServiceRequestState::canTransition($from, $toStatus)) throw ValidationException::withMessages(['status' => "Request cannot move from {$from} to {$toStatus}."]);
            $updates = ['status' => $toStatus];
            if (array_key_exists('assigned_user_id', $attributes)) $updates['assigned_user_id'] = $attributes['assigned_user_id'];
            if ($toStatus === 'triaged') $updates['triaged_at'] = now();
            if ($toStatus === 'resolved') $updates['resolved_at'] = now();
            if ($toStatus === 'closed') $updates['closed_at'] = now();
            $request->update($updates);
            return $request->fresh();
        });
    }

    public function linkWorkCore(PremiseSelfServiceRequest $request, string $linkedModule, string $linkedId): PremiseSelfServiceRequest
    {
        return DB::transaction(function () use ($request, $linkedModule, $linkedId): PremiseSelfServiceRequest {
            $request = $this->locked($request);
            if (ServiceRequestState::isTerminal($request->status)) throw ValidationException::withMessages(['request' => 'Closed requests cannot be linked to new work.']);
            $request->update(['workcore_linked_module' => $linkedModule, 'workcore_linked_id' => $linkedId, 'workcore_linked_at' => now()]);
            if (ServiceRequestState::canTransition($request->status, 'work_requested')) $request->update(['status' => 'work_requested']);
            event(new PremiseSelfServiceRequestWorkLinked($request));
            return $request->fresh();
        });
    }

    public function visibleToPortal(PremisePortalGrant $grant)
    {
        return PremiseSelfServiceRequest::withoutGlobalScopes()->where('company_id', $grant->company_id)->where('portal_grant_id', $grant->id)
            ->latest('submitted_at')->get(['id', 'request_number', 'request_type', 'priority', 'title', 'description', 'status', 'submitted_at', 'resolved_at', 'closed_at']);
    }

    public function premiseRequests(Property $premise)
    {
        return PremiseSelfServiceRequest::where('company_id', $premise->company_id)->where('premise_id', $premise->id)->latest('submitted_at')->get();
    }

    private function locked(PremiseSelfServiceRequest $request): PremiseSelfServiceRequest { return PremiseSelfServiceRequest::withoutGlobalScopes()->where('company_id', $request->company_id)->whereKey($request->id)->lockForUpdate()->firstOrFail(); }
}
