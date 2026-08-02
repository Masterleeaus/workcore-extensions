<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementFinanceLinkType;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreementFinanceLink;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAgreementFinanceLinked;

class PremiseAgreementFinanceLinkService
{
    public function attach(PremiseAgreement $agreement, array $attributes): PremiseAgreementFinanceLink
    {
        return DB::transaction(function () use ($agreement, $attributes) {
            $linkType = (string) ($attributes['link_type'] ?? '');
            if (!AgreementFinanceLinkType::isSupported($linkType)) {
                throw ValidationException::withMessages([
                    'link_type' => 'Unsupported agreement finance link type.',
                ]);
            }

            $externalId = trim((string) ($attributes['external_id'] ?? ''));
            $externalReference = trim((string) ($attributes['external_reference'] ?? ''));
            if ($externalId === '' && $externalReference === '') {
                throw ValidationException::withMessages([
                    'external_reference' => 'Provide a Titan Money or external reference.',
                ]);
            }
            if ($externalId === '') {
                $externalId = $externalReference;
            }

            $link = PremiseAgreementFinanceLink::updateOrCreate(
                [
                    'company_id' => $agreement->company_id,
                    'agreement_id' => $agreement->id,
                    'provider' => $attributes['provider'] ?? 'titan_money',
                    'link_type' => $linkType,
                    'external_id' => $externalId,
                ],
                [
                    'user_id' => $attributes['user_id'] ?? $this->actorId(),
                    'external_reference' => $externalReference ?: $externalId,
                    'status' => $attributes['status'] ?? 'linked',
                    'linked_at' => $attributes['linked_at'] ?? now(),
                    'metadata' => $attributes['metadata'] ?? null,
                ]
            );

            $this->syncLegacyOccupancyReference($agreement, $link);
            event(new PremiseAgreementFinanceLinked($link));

            return $link->fresh('agreement');
        });
    }

    public function deactivate(PremiseAgreementFinanceLink $link): PremiseAgreementFinanceLink
    {
        return DB::transaction(function () use ($link) {
            $link->update(['status' => 'inactive']);
            $this->syncLegacyOccupancyAfterDeactivation($link);
            return $link->fresh();
        });
    }

    private function syncLegacyOccupancyAfterDeactivation(PremiseAgreementFinanceLink $link): void
    {
        $agreement = $link->agreement;
        if (!$agreement || !$agreement->occupancy_id) {
            return;
        }

        $column = null;
        if (AgreementFinanceLinkType::isCompatibilityBillingReference($link->link_type)) {
            $column = 'billing_account_reference';
        } elseif (AgreementFinanceLinkType::isCompatibilityDepositReference($link->link_type)) {
            $column = 'deposit_reference';
        }

        if (!$column) {
            return;
        }

        $replacement = PremiseAgreementFinanceLink::withoutGlobalScopes()
            ->where('company_id', $agreement->company_id)
            ->where('agreement_id', $agreement->id)
            ->where('id', '!=', $link->id)
            ->where('status', 'linked')
            ->where(function ($query) use ($column) {
                if ($column === 'billing_account_reference') {
                    $query->where('link_type', 'billing_account');
                } else {
                    $query->whereIn('link_type', ['deposit', 'bond']);
                }
            })
            ->latest('id')
            ->first();

        PremiseOccupancy::withoutGlobalScopes()
            ->where('company_id', $agreement->company_id)
            ->whereKey($agreement->occupancy_id)
            ->update([
                $column => $replacement?->external_reference ?: $replacement?->external_id,
            ]);
    }

    private function syncLegacyOccupancyReference(
        PremiseAgreement $agreement,
        PremiseAgreementFinanceLink $link
    ): void {
        if (!$agreement->occupancy_id || $link->status !== 'linked') {
            return;
        }

        $reference = $link->external_reference ?: $link->external_id;
        $updates = [];
        if (AgreementFinanceLinkType::isCompatibilityBillingReference($link->link_type)) {
            $updates['billing_account_reference'] = $reference;
        }
        if (AgreementFinanceLinkType::isCompatibilityDepositReference($link->link_type)) {
            $updates['deposit_reference'] = $reference;
        }

        if ($updates) {
            PremiseOccupancy::withoutGlobalScopes()
                ->where('company_id', $agreement->company_id)
                ->whereKey($agreement->occupancy_id)
                ->update($updates);
        }
    }

    private function actorId(): ?int
    {
        try {
            return user()->id ?? auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }
}
