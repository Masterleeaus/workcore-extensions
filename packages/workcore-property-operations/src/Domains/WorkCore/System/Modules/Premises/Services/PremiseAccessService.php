<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Access\AccessItemState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessAuthorisation;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessEvent;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessItem;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAccessItemChanged;

class PremiseAccessService
{
    public function create(Property $premise, array $attributes): PremiseAccessItem
    {
        return DB::transaction(function () use ($premise, $attributes) {
            $status = $attributes['status'] ?? 'available';
            if (!AccessItemState::canCreate($status)) {
                throw ValidationException::withMessages(['status' => 'Access items must begin as available or issued.']);
            }

            $partyRole = $this->partyRole($premise, $attributes['current_party_role_id'] ?? null);
            if ($status === 'issued' && !$partyRole && empty($attributes['current_holder_name'])) {
                throw ValidationException::withMessages(['current_holder_name' => 'An issued access item requires a holder.']);
            }

            $item = PremiseAccessItem::create(array_merge($attributes, [
                'company_id' => $premise->company_id,
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'premise_id' => $premise->id,
                'current_party_role_id' => $partyRole?->id,
                'current_holder_name' => $partyRole?->display_name ?? ($attributes['current_holder_name'] ?? null),
                'issued_at' => $status === 'issued' ? ($attributes['issued_at'] ?? now()) : null,
                'status' => $status,
            ]));

            $this->recordEvent($item, $status === 'issued' ? 'issued' : 'created', [
                'party_role_id' => $partyRole?->id,
                'holder_display_name' => $item->current_holder_name,
                'expected_return_at' => $item->expected_return_at,
                'notes' => $attributes['event_notes'] ?? null,
            ]);
            event(new PremiseAccessItemChanged($item, $status === 'issued' ? 'issued' : 'created'));
            return $item->fresh(['space', 'currentPartyRole', 'events']);
        });
    }

    public function issue(PremiseAccessItem $item, array $attributes): PremiseAccessItem
    {
        return DB::transaction(function () use ($item, $attributes) {
            $item = $this->lockedItem($item);
            if (!AccessItemState::canTransition($item->status, 'issued')) {
                throw ValidationException::withMessages(['status' => "Access item cannot be issued from {$item->status}. "]);
            }
            $premise = Property::withoutGlobalScopes()->where('company_id', $item->company_id)->findOrFail($item->premise_id);
            $partyRole = $this->partyRole($premise, $attributes['party_role_id'] ?? null);
            $holderName = $partyRole?->display_name ?? ($attributes['holder_display_name'] ?? null);
            if (!$holderName) {
                throw ValidationException::withMessages(['holder_display_name' => 'Select or enter the person receiving this access item.']);
            }

            $item->update([
                'status' => 'issued',
                'current_party_role_id' => $partyRole?->id,
                'current_holder_name' => $holderName,
                'issued_at' => $attributes['occurred_at'] ?? now(),
                'expected_return_at' => $attributes['expected_return_at'] ?? null,
            ]);
            $this->recordEvent($item, 'issued', [
                'party_role_id' => $partyRole?->id,
                'holder_display_name' => $holderName,
                'occurred_at' => $attributes['occurred_at'] ?? now(),
                'expected_return_at' => $attributes['expected_return_at'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);
            event(new PremiseAccessItemChanged($item, 'issued'));
            return $item->fresh(['space', 'currentPartyRole', 'events']);
        });
    }

    public function transition(PremiseAccessItem $item, string $toStatus, array $attributes = []): PremiseAccessItem
    {
        return DB::transaction(function () use ($item, $toStatus, $attributes) {
            $item = $this->lockedItem($item);
            $previous = $item->status;
            if (!AccessItemState::canTransition($previous, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Access item cannot move from {$previous} to {$toStatus}. "]);
            }

            $clearHolder = in_array($toStatus, ['returned', 'available', 'lost', 'revoked', 'expired', 'decommissioned'], true);
            $item->update([
                'status' => $toStatus,
                'current_party_role_id' => $clearHolder ? null : $item->current_party_role_id,
                'current_holder_name' => $clearHolder ? null : $item->current_holder_name,
                'issued_at' => $clearHolder ? null : $item->issued_at,
                'expected_return_at' => $clearHolder ? null : $item->expected_return_at,
            ]);

            $eventType = match (true) {
                $previous === 'lost' && $toStatus === 'available' => 'found',
                $toStatus === 'available' => 'audited',
                default => $toStatus,
            };
            $this->recordEvent($item, $eventType, [
                'occurred_at' => $attributes['occurred_at'] ?? now(),
                'notes' => $attributes['notes'] ?? null,
            ]);
            event(new PremiseAccessItemChanged($item, $eventType));
            return $item->fresh(['space', 'currentPartyRole', 'events']);
        });
    }

    public function authorise(Property $premise, array $attributes): PremiseAccessAuthorisation
    {
        $partyRole = $this->partyRole($premise, $attributes['party_role_id'] ?? null);
        $subjectName = $partyRole?->display_name ?? ($attributes['subject_name'] ?? null);
        if (!$subjectName) {
            throw ValidationException::withMessages(['subject_name' => 'An authorised person or organisation is required.']);
        }

        return PremiseAccessAuthorisation::create(array_merge($attributes, [
            'company_id' => $premise->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $premise->id,
            'party_role_id' => $partyRole?->id,
            'subject_name' => $subjectName,
            'status' => 'active',
        ]));
    }

    public function revokeAuthorisation(PremiseAccessAuthorisation $authorisation): PremiseAccessAuthorisation
    {
        if (in_array($authorisation->status, ['revoked', 'cancelled'], true)) {
            return $authorisation;
        }
        $authorisation->update(['status' => 'revoked', 'revoked_at' => now()]);
        return $authorisation->fresh();
    }

    private function lockedItem(PremiseAccessItem $item): PremiseAccessItem
    {
        return PremiseAccessItem::withoutGlobalScopes()
            ->where('company_id', $item->company_id)
            ->whereKey($item->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function partyRole(Property $premise, mixed $id): ?PremisePartyRole
    {
        if (!$id) return null;
        return PremisePartyRole::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->findOrFail($id);
    }

    private function recordEvent(PremiseAccessItem $item, string $eventType, array $attributes): PremiseAccessEvent
    {
        return PremiseAccessEvent::create(array_merge($attributes, [
            'company_id' => $item->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'access_item_id' => $item->id,
            'event_type' => $eventType,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]));
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
