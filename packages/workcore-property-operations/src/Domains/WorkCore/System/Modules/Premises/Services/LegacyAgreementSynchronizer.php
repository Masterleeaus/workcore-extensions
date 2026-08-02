<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;

/**
 * Keeps the legacy agreement_reference, billing_account_reference, and
 * deposit_reference occupancy fields compatible with the canonical agreement
 * register introduced in v1.4.0.
 */
class LegacyAgreementSynchronizer
{
    public function __construct(
        private readonly PremiseAgreementService $agreements,
        private readonly PremiseAgreementFinanceLinkService $financeLinks
    ) {}

    public function syncFromOccupancy(PremiseOccupancy $occupancy): ?PremiseAgreement
    {
        if (!$this->hasLegacyReferences($occupancy)) {
            return null;
        }

        $agreement = PremiseAgreement::withoutGlobalScopes()
            ->where('company_id', $occupancy->company_id)
            ->where('source_type', 'premise_occupancy')
            ->where('source_id', $occupancy->id)
            ->first();

        if (!$agreement) {
            $previousAgreementId = null;
            if ($occupancy->previous_occupancy_id) {
                $previousAgreementId = PremiseAgreement::withoutGlobalScopes()
                    ->where('company_id', $occupancy->company_id)
                    ->where('source_type', 'premise_occupancy')
                    ->where('source_id', $occupancy->previous_occupancy_id)
                    ->value('id');
            }

            $agreement = $this->agreements->create($occupancy->premise, [
                'user_id' => $occupancy->user_id,
                'space_id' => $occupancy->space_id,
                'occupancy_id' => $occupancy->id,
                'previous_agreement_id' => $previousAgreementId,
                'party_role_id' => $occupancy->party_role_id,
                'agreement_role' => $this->agreementRoleForOccupancy($occupancy->occupancy_type),
                'agreement_type' => $this->agreementTypeForOccupancy($occupancy->occupancy_type),
                'status' => $this->initialAgreementStatus($occupancy->status),
                'reference' => $occupancy->agreement_reference,
                'title' => $occupancy->display_name . ' agreement',
                'start_date' => $occupancy->start_date,
                'end_date' => $occupancy->end_date,
                'source_type' => 'premise_occupancy',
                'source_id' => $occupancy->id,
                'metadata' => [
                    'compatibility_source' => 'pm_premise_occupancies',
                    'occupancy_status' => $occupancy->status,
                ],
            ]);
        } else {
            $this->syncAgreementLifecycle($agreement, $occupancy);
            $agreement->update([
                'space_id' => $occupancy->space_id,
                'reference' => $occupancy->agreement_reference ?: $agreement->reference,
                'start_date' => $occupancy->start_date ?: $agreement->start_date,
                'end_date' => $occupancy->end_date ?: $agreement->end_date,
                'metadata' => array_replace($agreement->metadata ?? [], [
                    'compatibility_source' => 'pm_premise_occupancies',
                    'occupancy_status' => $occupancy->status,
                ]),
            ]);
        }

        $this->syncFinanceReference($agreement, 'billing_account', $occupancy->billing_account_reference);
        $this->syncFinanceReference($agreement, 'deposit', $occupancy->deposit_reference);

        return $agreement->fresh(['parties', 'financeLinks']);
    }

    private function syncAgreementLifecycle(PremiseAgreement $agreement, PremiseOccupancy $occupancy): void
    {
        $target = $this->targetAgreementStatus($agreement, $occupancy->status);
        if ($target === $agreement->status) {
            return;
        }

        if (AgreementState::canTransition($agreement->status, $target)) {
            $this->agreements->transition($agreement, $target, [
                'start_date' => $occupancy->start_date,
                'end_date' => $occupancy->end_date,
                'terminated_at' => $occupancy->actual_end_date,
            ]);
        }
    }

    private function syncFinanceReference(PremiseAgreement $agreement, string $type, ?string $reference): void
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return;
        }

        $this->financeLinks->attach($agreement, [
            'provider' => 'external',
            'link_type' => $type,
            'external_id' => $reference,
            'external_reference' => $reference,
            'status' => 'linked',
            'metadata' => ['compatibility_source' => 'pm_premise_occupancies'],
        ]);
    }

    private function hasLegacyReferences(PremiseOccupancy $occupancy): bool
    {
        return trim((string) $occupancy->agreement_reference) !== ''
            || trim((string) $occupancy->billing_account_reference) !== ''
            || trim((string) $occupancy->deposit_reference) !== '';
    }

    private function initialAgreementStatus(string $occupancyStatus): string
    {
        return match ($occupancyStatus) {
            'applicant' => 'draft',
            'reserved', 'pending' => 'offered',
            'active', 'notice_given', 'ending', 'suspended' => 'active',
            default => 'draft',
        };
    }

    private function targetAgreementStatus(PremiseAgreement $agreement, string $occupancyStatus): string
    {
        return match ($occupancyStatus) {
            'applicant' => $agreement->status,
            'reserved', 'pending' => in_array($agreement->status, ['draft', 'offered'], true) ? 'offered' : $agreement->status,
            'active', 'suspended' => 'active',
            'notice_given' => 'notice_given',
            'ending' => 'ending',
            'vacated' => 'expired',
            'cancelled' => in_array($agreement->status, ['draft', 'offered', 'pending_signature'], true)
                ? 'cancelled'
                : 'terminated',
            default => $agreement->status,
        };
    }

    private function agreementTypeForOccupancy(string $occupancyType): string
    {
        return match ($occupancyType) {
            'resident' => 'residency_agreement',
            'tenant' => 'residential_lease',
            'storage_customer' => 'storage_agreement',
            'business_occupier' => 'commercial_lease',
            'licensee' => 'licence_to_occupy',
            'staff_allocation', 'internal_allocation' => 'internal_allocation',
            default => 'custom',
        };
    }

    private function agreementRoleForOccupancy(string $occupancyType): string
    {
        return match ($occupancyType) {
            'resident' => 'resident',
            'tenant' => 'tenant',
            'storage_customer' => 'storage_customer',
            'business_occupier' => 'business_occupier',
            'licensee' => 'licensee',
            default => 'occupant',
        };
    }
}
