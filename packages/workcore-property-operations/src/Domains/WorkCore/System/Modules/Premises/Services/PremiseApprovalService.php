<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Approvals\ApprovalPayloadSanitizer;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Approvals\ApprovalRequestState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApprovalDecision;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApprovalRequest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalGrant;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyApproval;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApprovalStatusChanged;

class PremiseApprovalService
{
    public function create(Property $premise, array $attributes): PremiseApprovalRequest
    {
        $this->assertLinks($premise, $attributes);
        if (!empty($attributes['requested_party_role_id']) && (int) ($attributes['required_decisions'] ?? 1) > 1) {
            throw ValidationException::withMessages(['required_decisions' => 'A request assigned to one external party can require only one decision.']);
        }
        return PremiseApprovalRequest::create([
            'company_id' => $premise->company_id, 'premise_id' => $premise->id,
            'portfolio_id' => $attributes['portfolio_id'] ?? null,
            'requested_by_user_id' => $attributes['requested_by_user_id'] ?? $this->actorId(),
            'requested_party_role_id' => $attributes['requested_party_role_id'] ?? null,
            'request_type' => $attributes['request_type'] ?? 'other',
            'linked_module' => $attributes['linked_module'] ?? null,
            'linked_id' => $attributes['linked_id'] ?? null,
            'title' => $attributes['title'], 'summary' => $attributes['summary'] ?? null,
            'status' => 'draft', 'required_decisions' => max(1, (int) ($attributes['required_decisions'] ?? 1)),
            'payload_snapshot' => ApprovalPayloadSanitizer::sanitize($attributes['payload_snapshot'] ?? []),
            'due_at' => $attributes['due_at'] ?? null, 'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function submit(PremiseApprovalRequest $request): PremiseApprovalRequest
    {
        return $this->transition($request, 'pending', ['requested_at' => now()]);
    }

    public function decide(PremiseApprovalRequest $request, string $decision, ?int $partyRoleId = null, ?int $userId = null, ?string $note = null, array $metadata = []): PremiseApprovalRequest
    {
        if (!in_array($decision, ['approve', 'reject'], true)) throw ValidationException::withMessages(['decision' => 'Decision must be approve or reject.']);
        return DB::transaction(function () use ($request, $decision, $partyRoleId, $userId, $note, $metadata) {
            $request = PremiseApprovalRequest::withoutGlobalScopes()->where('company_id', $request->company_id)->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status !== 'pending') throw ValidationException::withMessages(['status' => 'Only pending approval requests can receive decisions.']);
            if ($request->requested_party_role_id && (int) $request->requested_party_role_id !== (int) $partyRoleId) throw ValidationException::withMessages(['party_role_id' => 'This approval request is assigned to a different party.']);
            $userId ??= $this->actorId();
            $duplicate = PremiseApprovalDecision::withoutGlobalScopes()->where('approval_request_id', $request->id)
                ->where(function ($query) use ($partyRoleId, $userId) {
                    if ($partyRoleId) $query->where('party_role_id', $partyRoleId);
                    elseif ($userId) $query->where('user_id', $userId);
                    else $query->whereNull('party_role_id')->whereNull('user_id');
                })->exists();
            if ($duplicate) throw ValidationException::withMessages(['decision' => 'This approver has already decided this request.']);

            PremiseApprovalDecision::create([
                'company_id' => $request->company_id, 'approval_request_id' => $request->id,
                'party_role_id' => $partyRoleId, 'user_id' => $userId,
                'decision' => $decision, 'note' => $note, 'decided_at' => now(),
                'metadata' => ApprovalPayloadSanitizer::sanitize($metadata),
            ]);

            $from = $request->status;
            if ($decision === 'reject') {
                $request->update(['status' => 'rejected', 'decided_at' => now()]);
            } else {
                $approvals = PremiseApprovalDecision::withoutGlobalScopes()->where('approval_request_id', $request->id)->where('decision', 'approve')->count();
                if ($approvals >= $request->required_decisions) $request->update(['status' => 'approved', 'decided_at' => now()]);
            }
            if ($request->status !== $from) event(new PremiseApprovalStatusChanged($request, $from));
            $this->syncLegacy($request);
            return $request->fresh('decisions');
        });
    }

    public function markImplemented(PremiseApprovalRequest $request): PremiseApprovalRequest
    {
        return $this->transition($request, 'implemented', ['implemented_at' => now()]);
    }

    public function withdraw(PremiseApprovalRequest $request): PremiseApprovalRequest
    {
        return $this->transition($request, 'withdrawn', ['withdrawn_at' => now()]);
    }

    public function importLegacy(PropertyApproval $legacy): PremiseApprovalRequest
    {
        return PremiseApprovalRequest::withoutGlobalScopes()->firstOrCreate(
            ['legacy_property_approval_id' => $legacy->id],
            [
                'company_id' => $legacy->company_id, 'premise_id' => $legacy->property_id,
                'requested_by_user_id' => $legacy->requested_by, 'request_type' => 'other',
                'title' => $legacy->subject, 'status' => in_array($legacy->status, ['pending','approved','rejected'], true) ? $legacy->status : 'pending',
                'required_decisions' => 1, 'payload_snapshot' => ApprovalPayloadSanitizer::sanitize($legacy->request_payload ?? []),
                'requested_at' => $legacy->requested_at, 'decided_at' => $legacy->decided_at,
                'metadata' => ['source' => 'legacy_property_approval'],
            ]
        );
    }

    public function visibleToPortal(PremisePortalGrant $grant)
    {
        if (!$grant->allows('view_approval_requests') || !$grant->party_role_id) return collect();
        return PremiseApprovalRequest::withoutGlobalScopes()->where('company_id', $grant->company_id)
            ->where('premise_id', $grant->premise_id)->where('requested_party_role_id', $grant->party_role_id)
            ->whereIn('status', ['pending', 'approved', 'rejected', 'implemented'])
            ->latest('id')->limit(50)->get(['id','request_type','title','summary','status','requested_at','due_at','decided_at']);
    }

    public function decideFromPortal(PremisePortalGrant $grant, PremiseApprovalRequest $request, string $decision, ?string $note = null): PremiseApprovalRequest
    {
        if (!$grant->allows('decide_approval_requests')) abort(403);
        if ((int) $request->company_id !== (int) $grant->company_id || (int) $request->premise_id !== (int) $grant->premise_id || (int) $request->requested_party_role_id !== (int) $grant->party_role_id) abort(403);
        return $this->decide($request, $decision, $grant->party_role_id, null, $note, ['source' => 'portal', 'portal_grant_id' => $grant->id]);
    }

    private function transition(PremiseApprovalRequest $request, string $toStatus, array $updates = []): PremiseApprovalRequest
    {
        return DB::transaction(function () use ($request, $toStatus, $updates) {
            $request = PremiseApprovalRequest::withoutGlobalScopes()->where('company_id', $request->company_id)->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $from = $request->status;
            if (!ApprovalRequestState::canTransition($from, $toStatus)) throw ValidationException::withMessages(['status' => "Approval request cannot move from {$from} to {$toStatus}."]);
            $request->update(array_merge(['status' => $toStatus], $updates));
            event(new PremiseApprovalStatusChanged($request, $from));
            $this->syncLegacy($request);
            return $request->fresh();
        });
    }

    private function assertLinks(Property $premise, array $attributes): void
    {
        if (!empty($attributes['requested_party_role_id'])) {
            $exists = DB::table('pm_premise_party_roles')->where('id', $attributes['requested_party_role_id'])->where('company_id', $premise->company_id)->where('premise_id', $premise->id)->exists();
            if (!$exists) throw ValidationException::withMessages(['requested_party_role_id' => 'The requested approver does not belong to this premise.']);
        }
        if (!empty($attributes['portfolio_id'])) {
            $member = DB::table('pm_premise_portfolio_memberships')->where('portfolio_id', $attributes['portfolio_id'])->where('company_id', $premise->company_id)->where('premise_id', $premise->id)->whereNull('valid_until')->exists();
            if (!$member) throw ValidationException::withMessages(['portfolio_id' => 'The selected portfolio does not contain this premise.']);
        }
    }

    private function syncLegacy(PremiseApprovalRequest $request): void
    {
        if (!$request->legacy_property_approval_id) return;
        PropertyApproval::withoutGlobalScopes()->whereKey($request->legacy_property_approval_id)->update([
            'status' => $request->status === 'pending' ? 'pending' : $request->status,
            'decided_at' => $request->decided_at,
        ]);
    }

    private function actorId(): ?int { return function_exists('user') && user() ? user()->id : auth()->id(); }
}
