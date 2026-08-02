<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Portfolio\ManagementAuthorityState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseManagementAuthority;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseManagementAuthorityStatusChanged;

class PremiseManagementAuthorityService
{
    public function create(Property $premise, PremisePartyRole $partyRole, array $attributes): PremiseManagementAuthority
    {
        $this->assertManagerRole($premise, $partyRole);
        $this->assertLinkedRecords($premise, $attributes);
        return PremiseManagementAuthority::create([
            'company_id' => $premise->company_id, 'premise_id' => $premise->id,
            'party_role_id' => $partyRole->id, 'agreement_id' => $attributes['agreement_id'] ?? null,
            'document_id' => $attributes['document_id'] ?? null,
            'authority_type' => $attributes['authority_type'], 'status' => 'draft',
            'start_date' => $attributes['start_date'] ?? null, 'end_date' => $attributes['end_date'] ?? null,
            'notice_period_days' => $attributes['notice_period_days'] ?? null,
            'authorised_actions' => $attributes['authorised_actions'] ?? [],
            'restrictions' => $attributes['restrictions'] ?? [],
            'titan_money_policy_reference' => $attributes['titan_money_policy_reference'] ?? null,
            'external_reference' => $attributes['external_reference'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function transition(PremiseManagementAuthority $authority, string $toStatus, array $attributes = []): PremiseManagementAuthority
    {
        return DB::transaction(function () use ($authority, $toStatus, $attributes) {
            $authority = PremiseManagementAuthority::withoutGlobalScopes()->where('company_id', $authority->company_id)->whereKey($authority->id)->lockForUpdate()->firstOrFail();
            $from = $authority->status;
            if (!ManagementAuthorityState::canTransition($from, $toStatus)) throw ValidationException::withMessages(['status' => "Management authority cannot move from {$from} to {$toStatus}."]);
            $updates = ['status' => $toStatus];
            if ($toStatus === 'active') {
                if (!$authority->start_date && empty($attributes['start_date'])) throw ValidationException::withMessages(['start_date' => 'An active management authority requires a start date.']);
                $updates['start_date'] = $attributes['start_date'] ?? $authority->start_date;
                $updates['approved_by_user_id'] = $attributes['approved_by_user_id'] ?? $this->actorId();
                $updates['approved_at'] = now();
            }
            if ($toStatus === 'notice_given') $updates['notice_given_at'] = $attributes['notice_given_at'] ?? today()->toDateString();
            if ($toStatus === 'ended') $updates['ended_at'] = $attributes['ended_at'] ?? today()->toDateString();
            $authority->update($updates);
            event(new PremiseManagementAuthorityStatusChanged($authority, $from));
            return $authority->fresh();
        });
    }

    private function assertLinkedRecords(Property $premise, array $attributes): void
    {
        foreach (['agreement_id' => 'pm_premise_agreements', 'document_id' => 'pm_property_documents'] as $field => $table) {
            if (empty($attributes[$field])) continue;
            $query = DB::table($table)->where('id', $attributes[$field])->where('company_id', $premise->company_id);
            $table === 'pm_property_documents' ? $query->where('property_id', $premise->id) : $query->where('premise_id', $premise->id);
            if (!$query->exists()) throw ValidationException::withMessages([$field => 'The linked record does not belong to this premise.']);
        }
    }

    private function assertManagerRole(Property $premise, PremisePartyRole $partyRole): void
    {
        if ((int) $partyRole->company_id !== (int) $premise->company_id || (int) $partyRole->premise_id !== (int) $premise->id) {
            throw ValidationException::withMessages(['party_role_id' => 'The selected party role does not belong to this premise.']);
        }
        if (!in_array($partyRole->role, ['owner', 'property_manager', 'real_estate_agent', 'facility_manager'], true)) {
            throw ValidationException::withMessages(['party_role_id' => 'Management authority requires an owner or management party role.']);
        }
    }

    private function actorId(): ?int { return function_exists('user') && user() ? user()->id : auth()->id(); }
}
