<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreementParty;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAgreementCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAgreementRenewed;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAgreementStatusChanged;

class PremiseAgreementService
{
    public function create(Property $premise, array $attributes): PremiseAgreement
    {
        return DB::transaction(function () use ($premise, $attributes) {
            $status = (string) ($attributes['status'] ?? 'draft');
            $this->assertKnownStatus($status);

            if (!AgreementState::canCreate($status)) {
                throw ValidationException::withMessages([
                    'status' => 'A new agreement must begin as draft, offered, pending signature, or active.',
                ]);
            }

            $space = $this->resolveSpace($premise, $attributes['space_id'] ?? null);
            $occupancy = $this->resolveOccupancy($premise, $attributes['occupancy_id'] ?? null);
            $previousAgreement = $this->resolvePreviousAgreement($premise, $attributes['previous_agreement_id'] ?? null);
            $partyRole = $this->resolvePartyRole($premise, $attributes['party_role_id'] ?? null);

            if ($occupancy && $space && (int) $occupancy->space_id !== (int) $space->id) {
                throw ValidationException::withMessages([
                    'space_id' => 'The selected occupancy belongs to a different space.',
                ]);
            }

            $space = $space ?: $occupancy?->space;
            $reference = $this->normaliseReference($attributes['reference'] ?? null);
            $title = trim((string) ($attributes['title'] ?? ''));
            if ($title === '') {
                $title = $this->defaultTitle($attributes['agreement_type'] ?? 'custom', $partyRole, $space);
            }

            $agreement = PremiseAgreement::create([
                'company_id' => $premise->company_id,
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'premise_id' => $premise->id,
                'space_id' => $space?->id,
                'occupancy_id' => $occupancy?->id,
                'previous_agreement_id' => $previousAgreement?->id,
                'agreement_type' => $attributes['agreement_type'] ?? 'custom',
                'status' => $status,
                'reference' => $reference,
                'title' => $title,
                'offered_at' => $attributes['offered_at'] ?? ($status === 'offered' ? now()->toDateString() : null),
                'signed_at' => $attributes['signed_at'] ?? ($status === 'active' ? now()->toDateString() : null),
                'start_date' => $attributes['start_date'] ?? ($status === 'active' ? now()->toDateString() : null),
                'end_date' => $attributes['end_date'] ?? null,
                'notice_given_at' => $attributes['notice_given_at'] ?? null,
                'terminated_at' => $attributes['terminated_at'] ?? null,
                'renewal_type' => $attributes['renewal_type'] ?? 'fixed',
                'notice_period_days' => $attributes['notice_period_days'] ?? null,
                'auto_renew' => (bool) ($attributes['auto_renew'] ?? false),
                'document_id' => $attributes['document_id'] ?? null,
                'document_reference' => $attributes['document_reference'] ?? null,
                'terms_summary' => $attributes['terms_summary'] ?? null,
                'source_type' => $attributes['source_type'] ?? null,
                'source_id' => $attributes['source_id'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            if ($partyRole) {
                $this->addParty($agreement, $partyRole, [
                    'agreement_role' => $attributes['agreement_role'] ?? $this->defaultAgreementRole($partyRole),
                    'signing_status' => $attributes['signing_status'] ?? ($status === 'active' ? 'signed' : 'pending'),
                    'signed_at' => $attributes['party_signed_at'] ?? ($status === 'active' ? now() : null),
                    'is_primary' => true,
                ]);
            }

            if ($status === 'active') {
                $this->activateAgreement($agreement);
            }

            event(new PremiseAgreementCreated($agreement));

            return $agreement->fresh(['space', 'occupancy', 'parties.partyRole', 'financeLinks', 'previousAgreement']);
        });
    }

    public function transition(PremiseAgreement $agreement, string $toStatus, array $attributes = []): PremiseAgreement
    {
        return DB::transaction(function () use ($agreement, $toStatus, $attributes) {
            $agreement = $this->lockedAgreement($agreement);
            $previousStatus = $agreement->status;
            $this->assertKnownStatus($toStatus);

            if (!AgreementState::canTransition($previousStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Agreement cannot move from {$previousStatus} to {$toStatus}.",
                ]);
            }

            $updates = ['status' => $toStatus];
            foreach ([
                'offered_at', 'signed_at', 'start_date', 'end_date', 'notice_given_at',
                'terminated_at', 'document_id', 'document_reference', 'terms_summary', 'renewal_type',
                'notice_period_days', 'auto_renew',
            ] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $updates[$field] = $attributes[$field];
                }
            }

            if ($toStatus === 'offered' && empty($updates['offered_at'])) {
                $updates['offered_at'] = now()->toDateString();
            }
            if ($toStatus === 'active') {
                $updates['signed_at'] = $updates['signed_at'] ?? $agreement->signed_at ?? now()->toDateString();
                $updates['start_date'] = $updates['start_date'] ?? $agreement->start_date ?? now()->toDateString();
            }
            if ($toStatus === 'notice_given' && empty($updates['notice_given_at'])) {
                $updates['notice_given_at'] = now()->toDateString();
            }
            if (in_array($toStatus, ['expired', 'terminated'], true) && empty($updates['terminated_at'])) {
                $updates['terminated_at'] = now()->toDateString();
            }

            $agreement->update($updates);

            if ($toStatus === 'active') {
                $this->activateAgreement($agreement);
            }

            event(new PremiseAgreementStatusChanged($agreement, $previousStatus));

            return $agreement->fresh(['space', 'occupancy', 'parties.partyRole', 'financeLinks', 'previousAgreement']);
        });
    }

    public function renew(PremiseAgreement $agreement, array $attributes = []): PremiseAgreement
    {
        return DB::transaction(function () use ($agreement, $attributes) {
            $agreement = $this->lockedAgreement($agreement);
            if (!AgreementState::canRenew($agreement->status)) {
                throw ValidationException::withMessages([
                    'agreement' => 'Only a current, ending, or expired agreement can be renewed.',
                ]);
            }

            $agreement->load('parties.partyRole');

            $newStatus = (string) ($attributes['status'] ?? 'draft');
            if (!AgreementState::canCreate($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'A renewal must begin as draft, offered, pending signature, or active.',
                ]);
            }

            $renewal = $this->create($agreement->premise, array_merge([
                'space_id' => $agreement->space_id,
                'occupancy_id' => $agreement->occupancy_id,
                'previous_agreement_id' => $agreement->id,
                'agreement_type' => $agreement->agreement_type,
                'title' => $agreement->title,
                'renewal_type' => $agreement->renewal_type,
                'notice_period_days' => $agreement->notice_period_days,
                'auto_renew' => $agreement->auto_renew,
                'document_id' => null,
                'document_reference' => null,
                'terms_summary' => $agreement->terms_summary,
                'metadata' => array_merge($agreement->metadata ?? [], ['renewed_from_agreement_id' => $agreement->id]),
            ], Arr::except($attributes, ['party_role_id']), [
                'party_role_id' => $attributes['party_role_id'] ?? $agreement->parties()->where('is_primary', true)->value('party_role_id'),
            ]));

            foreach ($agreement->parties as $existingParty) {
                if (!$existingParty->partyRole) {
                    continue;
                }

                $signingStatus = $existingParty->signing_status === 'not_required'
                    ? 'not_required'
                    : ($renewal->status === 'active' ? 'signed' : 'pending');

                $this->addParty($renewal, $existingParty->partyRole, [
                    'agreement_role' => $existingParty->agreement_role,
                    'signing_status' => $signingStatus,
                    'signed_at' => $signingStatus === 'signed' ? ($attributes['signed_at'] ?? now()) : null,
                    'is_primary' => $existingParty->is_primary,
                    'metadata' => array_replace($existingParty->metadata ?? [], [
                        'renewed_from_agreement_party_id' => $existingParty->id,
                    ]),
                ]);
            }

            event(new PremiseAgreementRenewed($renewal, $agreement));

            return $renewal->fresh(['space', 'occupancy', 'parties.partyRole', 'financeLinks', 'previousAgreement']);
        });
    }

    public function addParty(
        PremiseAgreement $agreement,
        PremisePartyRole $partyRole,
        array $attributes = []
    ): PremiseAgreementParty {
        $this->assertPartyScoped($agreement, $partyRole);

        if (!empty($attributes['is_primary'])) {
            PremiseAgreementParty::where('company_id', $agreement->company_id)
                ->where('agreement_id', $agreement->id)
                ->where('agreement_role', $attributes['agreement_role'] ?? 'other')
                ->update(['is_primary' => false]);
        }

        return PremiseAgreementParty::updateOrCreate(
            [
                'company_id' => $agreement->company_id,
                'agreement_id' => $agreement->id,
                'party_role_id' => $partyRole->id,
                'agreement_role' => $attributes['agreement_role'] ?? 'other',
            ],
            [
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'party_type' => $partyRole->party_type,
                'party_id' => $partyRole->party_id,
                'display_name' => $partyRole->display_name,
                'signing_status' => $attributes['signing_status'] ?? 'not_required',
                'signed_at' => $attributes['signed_at'] ?? null,
                'is_primary' => (bool) ($attributes['is_primary'] ?? false),
                'metadata' => $attributes['metadata'] ?? null,
            ]
        );
    }

    public function removeParty(PremiseAgreementParty $agreementParty): void
    {
        $agreement = $agreementParty->agreement;
        if (!in_array($agreement->status, ['draft', 'offered', 'pending_signature'], true)) {
            throw ValidationException::withMessages([
                'party' => 'Agreement parties are retained after activation. Change their role record instead of deleting history.',
            ]);
        }
        if ($agreementParty->signing_status === 'signed') {
            throw ValidationException::withMessages([
                'party' => 'A signed agreement party cannot be deleted.',
            ]);
        }

        $agreementParty->delete();
    }

    private function activateAgreement(PremiseAgreement $agreement): void
    {
        if ($agreement->previous_agreement_id) {
            $previous = PremiseAgreement::withoutGlobalScopes()
                ->where('company_id', $agreement->company_id)
                ->whereKey($agreement->previous_agreement_id)
                ->lockForUpdate()
                ->first();

            if ($previous && AgreementState::isCurrent($previous->status)) {
                $previousStatus = $previous->status;
                $previous->update(['status' => 'superseded', 'terminated_at' => now()->toDateString()]);
                event(new PremiseAgreementStatusChanged($previous, $previousStatus));
            }
        }

        if ($agreement->occupancy_id) {
            PremiseAgreement::withoutGlobalScopes()
                ->where('company_id', $agreement->company_id)
                ->where('occupancy_id', $agreement->occupancy_id)
                ->where('agreement_type', $agreement->agreement_type)
                ->where('id', '!=', $agreement->id)
                ->whereIn('status', ['active', 'notice_given', 'ending'])
                ->update(['status' => 'superseded', 'terminated_at' => now()->toDateString()]);

            if ($agreement->reference) {
                PremiseOccupancy::withoutGlobalScopes()
                    ->where('company_id', $agreement->company_id)
                    ->whereKey($agreement->occupancy_id)
                    ->update(['agreement_reference' => $agreement->reference]);
            }
        }
    }

    private function lockedAgreement(PremiseAgreement $agreement): PremiseAgreement
    {
        return PremiseAgreement::withoutGlobalScopes()
            ->where('company_id', $agreement->company_id)
            ->whereKey($agreement->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolveSpace(Property $premise, ?int $spaceId): ?PremiseSpace
    {
        if (!$spaceId) {
            return null;
        }

        return PremiseSpace::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->findOrFail($spaceId);
    }

    private function resolveOccupancy(Property $premise, ?int $occupancyId): ?PremiseOccupancy
    {
        if (!$occupancyId) {
            return null;
        }

        return PremiseOccupancy::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->findOrFail($occupancyId);
    }

    private function resolvePreviousAgreement(Property $premise, ?int $agreementId): ?PremiseAgreement
    {
        if (!$agreementId) {
            return null;
        }

        return PremiseAgreement::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->findOrFail($agreementId);
    }

    private function resolvePartyRole(Property $premise, ?int $partyRoleId): ?PremisePartyRole
    {
        if (!$partyRoleId) {
            return null;
        }

        return PremisePartyRole::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->findOrFail($partyRoleId);
    }

    private function assertPartyScoped(PremiseAgreement $agreement, PremisePartyRole $partyRole): void
    {
        if ((int) $partyRole->company_id !== (int) $agreement->company_id
            || (int) $partyRole->premise_id !== (int) $agreement->premise_id) {
            throw ValidationException::withMessages([
                'party_role_id' => 'The selected party does not belong to this premise.',
            ]);
        }
    }

    private function assertKnownStatus(string $status): void
    {
        if (!in_array($status, AgreementState::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown agreement status.']);
        }
    }

    private function normaliseReference(?string $reference): ?string
    {
        $reference = trim((string) $reference);
        return $reference === '' ? null : $reference;
    }

    private function defaultTitle(string $agreementType, ?PremisePartyRole $partyRole, ?PremiseSpace $space): string
    {
        $label = str($agreementType)->replace('_', ' ')->title();
        $subject = $partyRole?->display_name ?: $space?->name;
        return $subject ? "{$label} — {$subject}" : (string) $label;
    }

    private function defaultAgreementRole(PremisePartyRole $partyRole): string
    {
        return in_array($partyRole->role, PremiseAgreementParty::AGREEMENT_ROLES, true)
            ? $partyRole->role
            : 'occupant';
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
