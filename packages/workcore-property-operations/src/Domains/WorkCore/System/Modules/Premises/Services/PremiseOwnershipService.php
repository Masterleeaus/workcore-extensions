<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Portfolio\OwnershipSharePolicy;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOwnershipInterest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseOwnershipChanged;

class PremiseOwnershipService
{
    public function create(Property $premise, PremisePartyRole $partyRole, array $attributes): PremiseOwnershipInterest
    {
        $this->assertOwnerRole($premise, $partyRole);
        $share = array_key_exists('share_percent', $attributes) && $attributes['share_percent'] !== null ? (float) $attributes['share_percent'] : null;
        if ($share !== null && !OwnershipSharePolicy::canAdd(0.0, $share)) throw ValidationException::withMessages(['share_percent' => 'Ownership share must be greater than zero and no more than 100.']);

        return DB::transaction(function () use ($premise, $partyRole, $attributes, $share) {
            if ($share !== null) {
                $activeShare = (float) PremiseOwnershipInterest::withoutGlobalScopes()->where('company_id', $premise->company_id)
                    ->where('premise_id', $premise->id)->whereNull('valid_until')->sum('share_percent');
                if (!OwnershipSharePolicy::canAdd($activeShare, $share)) throw ValidationException::withMessages(['share_percent' => 'Active ownership shares cannot exceed 100%.']);
            }
            if (!empty($attributes['is_primary'])) {
                PremiseOwnershipInterest::withoutGlobalScopes()->where('company_id', $premise->company_id)
                    ->where('premise_id', $premise->id)->whereNull('valid_until')->update(['is_primary' => false]);
            }
            $interest = PremiseOwnershipInterest::create([
                'company_id' => $premise->company_id, 'premise_id' => $premise->id,
                'party_role_id' => $partyRole->id, 'document_id' => $attributes['document_id'] ?? null,
                'ownership_type' => $attributes['ownership_type'] ?? 'sole', 'share_percent' => $share,
                'is_primary' => (bool) ($attributes['is_primary'] ?? false),
                'title_reference' => $attributes['title_reference'] ?? null,
                'external_reference' => $attributes['external_reference'] ?? null,
                'valid_from' => $attributes['valid_from'] ?? today()->toDateString(),
                'notes' => $attributes['notes'] ?? null, 'metadata' => $attributes['metadata'] ?? null,
            ]);
            event(new PremiseOwnershipChanged($interest, 'created'));
            return $interest;
        });
    }

    public function end(PremiseOwnershipInterest $interest, ?string $date = null): PremiseOwnershipInterest
    {
        if ($interest->valid_until) throw ValidationException::withMessages(['ownership' => 'This ownership interest has already ended.']);
        $interest->update(['valid_until' => $date ?: today()->toDateString()]);
        event(new PremiseOwnershipChanged($interest, 'ended'));
        return $interest->fresh();
    }

    private function assertOwnerRole(Property $premise, PremisePartyRole $partyRole): void
    {
        if ((int) $partyRole->company_id !== (int) $premise->company_id || (int) $partyRole->premise_id !== (int) $premise->id) {
            throw ValidationException::withMessages(['party_role_id' => 'The selected owner role does not belong to this premise.']);
        }
        if ($partyRole->role !== 'owner') throw ValidationException::withMessages(['party_role_id' => 'Ownership interests require an owner party role.']);
    }
}
