<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Access\AccessCardRequestState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessCardRequest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessCardRequestItem;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAccessCardRequestChanged;

class PremiseAccessCardRequestService
{
    public function __construct(private readonly PremiseAccessService $access)
    {
    }

    public function create(Property $premise, array $attributes): PremiseAccessCardRequest
    {
        return DB::transaction(function () use ($premise, $attributes): PremiseAccessCardRequest {
            $items = $attributes['items'] ?? [];
            if (!$items) {
                throw ValidationException::withMessages(['items' => 'At least one physical access card is required.']);
            }

            $request = PremiseAccessCardRequest::create([
                'company_id' => $premise->company_id,
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'premise_id' => $premise->id,
                'space_id' => $attributes['space_id'] ?? null,
                'requester_party_role_id' => $attributes['requester_party_role_id'] ?? null,
                'occupancy_id' => $attributes['occupancy_id'] ?? null,
                'agreement_id' => $attributes['agreement_id'] ?? null,
                'request_number' => 'CARD-' . now()->format('ymd') . '-' . Str::upper(Str::random(8)),
                'status' => 'draft',
                'requested_for_name' => $attributes['requested_for_name'] ?? null,
                'requested_for_contact' => $attributes['requested_for_contact'] ?? null,
                'purpose' => $attributes['purpose'] ?? null,
                'expected_return_at' => $attributes['expected_return_at'] ?? null,
                'fee_reference' => $attributes['fee_reference'] ?? null,
                'metadata' => $attributes['metadata'] ?? [],
            ]);

            foreach ($items as $item) {
                $partyRole = $this->partyRole($premise, $item['holder_party_role_id'] ?? null);
                $holderName = $partyRole?->display_name ?? ($item['holder_display_name'] ?? null);
                if (!$holderName) {
                    throw ValidationException::withMessages(['items' => 'Each requested card requires a holder.']);
                }
                PremiseAccessCardRequestItem::create([
                    'company_id' => $premise->company_id,
                    'access_card_request_id' => $request->id,
                    'holder_party_role_id' => $partyRole?->id,
                    'label' => $item['label'] ?? 'Physical access card',
                    'holder_display_name' => $holderName,
                    'status' => 'requested',
                    'notes' => $item['notes'] ?? null,
                    'metadata' => $item['metadata'] ?? [],
                ]);
            }

            return $request->fresh(['items', 'space', 'requesterPartyRole']);
        });
    }

    public function transition(PremiseAccessCardRequest $request, string $toStatus, array $attributes = []): PremiseAccessCardRequest
    {
        return DB::transaction(function () use ($request, $toStatus, $attributes): PremiseAccessCardRequest {
            $request = $this->lockedRequest($request);
            $from = (string) $request->status;
            if (!AccessCardRequestState::canTransition($from, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Access-card request cannot move from {$from} to {$toStatus}."]);
            }
            if (in_array($toStatus, ['partially_issued', 'issued'], true)) {
                throw ValidationException::withMessages(['status' => 'Issuance status is calculated from approved request items.']);
            }

            $updates = ['status' => $toStatus];
            if ($toStatus === 'submitted') $updates['submitted_at'] = now();
            if (in_array($toStatus, ['approved', 'rejected'], true)) {
                $updates['decided_by_user_id'] = $this->actorId();
                $updates['decided_at'] = now();
                $updates['decision_notes'] = $attributes['decision_notes'] ?? null;
            }
            $request->update($updates);
            event(new PremiseAccessCardRequestChanged($request, $toStatus));
            return $request->fresh(['items']);
        });
    }

    public function approveItem(PremiseAccessCardRequest $request, PremiseAccessCardRequestItem $item, array $attributes = []): PremiseAccessCardRequestItem
    {
        return DB::transaction(function () use ($request, $item, $attributes): PremiseAccessCardRequestItem {
            [$request, $item] = $this->lockedPair($request, $item);
            if (!in_array($request->status, ['approved', 'partially_issued'], true) || $item->status !== 'requested') {
                throw ValidationException::withMessages(['item' => 'Only requested cards in an approved request may be approved.']);
            }
            $item->update([
                'status' => 'approved',
                'approved_by_user_id' => $this->actorId(),
                'approved_at' => now(),
                'notes' => $attributes['notes'] ?? $item->notes,
            ]);
            return $item->fresh();
        });
    }

    public function rejectItem(PremiseAccessCardRequest $request, PremiseAccessCardRequestItem $item, array $attributes = []): PremiseAccessCardRequestItem
    {
        return DB::transaction(function () use ($request, $item, $attributes): PremiseAccessCardRequestItem {
            [$request, $item] = $this->lockedPair($request, $item);
            if (!in_array($request->status, ['approved', 'partially_issued'], true) || !in_array($item->status, ['requested', 'approved'], true)) {
                throw ValidationException::withMessages(['item' => 'This card item cannot be rejected from its current state.']);
            }
            $item->update(['status' => 'rejected', 'rejected_at' => now(), 'notes' => $attributes['notes'] ?? $item->notes]);
            $this->recalculateRequest($request);
            return $item->fresh();
        });
    }

    public function issueItem(PremiseAccessCardRequest $request, PremiseAccessCardRequestItem $item, array $attributes): PremiseAccessCardRequestItem
    {
        return DB::transaction(function () use ($request, $item, $attributes): PremiseAccessCardRequestItem {
            [$request, $item] = $this->lockedPair($request, $item);
            if (!in_array($request->status, ['approved', 'partially_issued'], true) || $item->status !== 'approved') {
                throw ValidationException::withMessages(['item' => 'The card item must be approved before issue.']);
            }
            $request->loadMissing('premise');
            $accessItem = $this->access->create($request->premise, [
                'user_id' => $this->actorId(),
                'space_id' => $request->space_id,
                'item_type' => 'access_card',
                'label' => $item->label,
                'identifier' => $attributes['identifier'],
                'status' => 'issued',
                'current_party_role_id' => $item->holder_party_role_id,
                'current_holder_name' => $item->holder_display_name,
                'issued_at' => $attributes['issued_at'] ?? now(),
                'expected_return_at' => $attributes['expected_return_at'] ?? $request->expected_return_at,
                'expires_at' => $attributes['expires_at'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'metadata' => ['access_card_request_id' => $request->id, 'access_card_request_item_id' => $item->id],
            ]);
            $item->update(['status' => 'issued', 'access_item_id' => $accessItem->id, 'issued_at' => $accessItem->issued_at]);
            $this->recalculateRequest($request);
            event(new PremiseAccessCardRequestChanged($request->fresh(), 'card_issued'));
            return $item->fresh(['accessItem']);
        });
    }

    public function returnItem(PremiseAccessCardRequest $request, PremiseAccessCardRequestItem $item, array $attributes = []): PremiseAccessCardRequestItem
    {
        return DB::transaction(function () use ($request, $item, $attributes): PremiseAccessCardRequestItem {
            [$request, $item] = $this->lockedPair($request, $item);
            if ($item->status !== 'issued' || !$item->access_item_id) {
                throw ValidationException::withMessages(['item' => 'Only an issued physical access card can be returned.']);
            }
            $accessItem = $item->accessItem()->withoutGlobalScopes()->where('company_id', $request->company_id)->firstOrFail();
            $this->access->transition($accessItem, 'returned', ['occurred_at' => $attributes['returned_at'] ?? now(), 'notes' => $attributes['notes'] ?? null]);
            $item->update(['status' => 'returned', 'returned_at' => $attributes['returned_at'] ?? now(), 'notes' => $attributes['notes'] ?? $item->notes]);
            $this->recalculateRequest($request);
            event(new PremiseAccessCardRequestChanged($request->fresh(), 'card_returned'));
            return $item->fresh(['accessItem']);
        });
    }

    private function recalculateRequest(PremiseAccessCardRequest $request): void
    {
        $request->load('items');
        $statuses = $request->items->pluck('status');
        $issued = $statuses->filter(fn ($status) => in_array($status, ['issued', 'returned', 'revoked'], true))->count();
        $open = $statuses->filter(fn ($status) => in_array($status, ['requested', 'approved', 'issued'], true))->count();
        $newStatus = $request->status;
        if ($issued > 0 && $open === 0) $newStatus = 'closed';
        elseif ($issued > 0 && $statuses->contains(fn ($status) => in_array($status, ['requested', 'approved'], true))) $newStatus = 'partially_issued';
        elseif ($issued > 0) $newStatus = 'issued';
        elseif ($statuses->isNotEmpty() && $statuses->every(fn ($status) => in_array($status, ['rejected', 'cancelled'], true))) $newStatus = 'closed';
        if ($newStatus !== $request->status && AccessCardRequestState::canTransition((string) $request->status, $newStatus)) {
            $request->update(['status' => $newStatus]);
        }
    }

    private function lockedRequest(PremiseAccessCardRequest $request): PremiseAccessCardRequest
    {
        return PremiseAccessCardRequest::withoutGlobalScopes()->where('company_id', $request->company_id)->whereKey($request->id)->lockForUpdate()->firstOrFail();
    }

    private function lockedPair(PremiseAccessCardRequest $request, PremiseAccessCardRequestItem $item): array
    {
        $request = $this->lockedRequest($request);
        $item = PremiseAccessCardRequestItem::withoutGlobalScopes()
            ->where('company_id', $request->company_id)
            ->where('access_card_request_id', $request->id)
            ->whereKey($item->id)
            ->lockForUpdate()->firstOrFail();
        return [$request, $item];
    }

    private function partyRole(Property $premise, mixed $id): ?PremisePartyRole
    {
        if (!$id) return null;
        return PremisePartyRole::withoutGlobalScopes()->where('company_id', $premise->company_id)->where('premise_id', $premise->id)->findOrFail($id);
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
