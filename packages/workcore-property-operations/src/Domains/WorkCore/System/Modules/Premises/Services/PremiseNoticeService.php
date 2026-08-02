<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Communications\NoticeState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseNotice;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalGrant;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseNoticeStatusChanged;

class PremiseNoticeService
{
    public function create(Property $premise, array $attributes): PremiseNotice
    {
        return PremiseNotice::create(array_merge($attributes, [
            'company_id' => $premise->company_id, 'premise_id' => $premise->id,
            'drafted_by_user_id' => $this->actorId(), 'status' => 'draft',
        ]));
    }

    public function transition(PremiseNotice $notice, string $toStatus, array $attributes = []): PremiseNotice
    {
        return DB::transaction(function () use ($notice, $toStatus, $attributes): PremiseNotice {
            $notice = $this->locked($notice);
            $from = $notice->status;
            if (!NoticeState::canTransition($from, $toStatus)) throw ValidationException::withMessages(['status' => "Notice cannot move from {$from} to {$toStatus}."]);
            if ($toStatus === 'issued' && $from !== 'approved') throw ValidationException::withMessages(['status' => 'A notice must be approved before it can be issued.']);
            $updates = ['status' => $toStatus];
            if ($toStatus === 'pending_approval') $updates['approval_requested_at'] = now();
            if ($toStatus === 'approved') { $updates['approved_at'] = now(); $updates['approved_by_user_id'] = $this->actorId(); }
            if ($toStatus === 'issued') { $updates['issued_at'] = now(); $updates['issued_by_user_id'] = $this->actorId(); }
            if ($toStatus === 'acknowledged') $updates['acknowledged_at'] = now();
            if ($toStatus === 'withdrawn') { $updates['withdrawn_at'] = now(); $updates['withdrawal_reason'] = $attributes['withdrawal_reason'] ?? null; }
            $notice->update($updates);
            event(new PremiseNoticeStatusChanged($notice));
            return $notice->fresh();
        });
    }

    public function acknowledgeFromPortal(PremisePortalGrant $grant, PremiseNotice $notice): PremiseNotice
    {
        if ((int) $notice->company_id !== (int) $grant->company_id || (int) $notice->premise_id !== (int) $grant->premise_id) abort(403);
        if ($notice->portal_grant_id && (int) $notice->portal_grant_id !== (int) $grant->id) abort(403);
        return $this->transition($notice, 'acknowledged');
    }

    public function visibleToPortal(PremisePortalGrant $grant)
    {
        return PremiseNotice::withoutGlobalScopes()->where('company_id', $grant->company_id)->where('premise_id', $grant->premise_id)
            ->whereIn('status', ['issued', 'acknowledged'])
            ->where(function ($query) use ($grant) {
                $query->where('portal_grant_id', $grant->id);
                if ($grant->party_role_id) $query->orWhere(function ($q) use ($grant) { $q->whereNull('portal_grant_id')->where('party_role_id', $grant->party_role_id); });
                if ($grant->occupancy_id) $query->orWhere(function ($q) use ($grant) { $q->whereNull('portal_grant_id')->where('occupancy_id', $grant->occupancy_id); });
                if ($grant->agreement_id) $query->orWhere(function ($q) use ($grant) { $q->whereNull('portal_grant_id')->where('agreement_id', $grant->agreement_id); });
                $query->orWhere(function ($q) { $q->whereNull('portal_grant_id')->whereNull('party_role_id')->whereNull('occupancy_id')->whereNull('agreement_id'); });
            })->orderByDesc('issued_at')->get(['id', 'notice_type', 'title', 'body', 'status', 'requires_acknowledgement', 'issued_at', 'acknowledged_at', 'expires_at']);
    }

    private function locked(PremiseNotice $notice): PremiseNotice { return PremiseNotice::withoutGlobalScopes()->where('company_id', $notice->company_id)->whereKey($notice->id)->lockForUpdate()->firstOrFail(); }
    private function actorId(): ?int { return function_exists('user') && user() ? user()->id : auth()->id(); }
}
