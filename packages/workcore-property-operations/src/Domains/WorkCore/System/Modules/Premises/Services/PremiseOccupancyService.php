<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyCapacityPolicy;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseOccupancyCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseOccupancyStatusChanged;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseOccupancyTransferred;

class PremiseOccupancyService
{
    public function __construct(private readonly LegacyAgreementSynchronizer $legacyAgreementSynchronizer) {}

    public function create(Property $premise, PremiseSpace $space, array $attributes): PremiseOccupancy
    {
        return DB::transaction(function () use ($premise, $space, $attributes) {
            $space = $this->lockedSpace($premise, $space);
            $partyRole = $this->resolvePartyRole($premise, $attributes['party_role_id'] ?? null);
            $status = $attributes['status'] ?? 'reserved';
            $capacityUsed = (int) ($attributes['capacity_used'] ?? 1);

            $this->assertKnownStatus($status);
            if (!OccupancyState::canCreate($status)) {
                throw ValidationException::withMessages([
                    'status' => 'A new occupancy must begin as an applicant, reservation, pending allocation, or active occupancy.',
                ]);
            }
            $this->assertSpaceCanReceiveOccupancy($space);

            if (OccupancyState::consumesCapacity($status)) {
                $this->assertCapacity($space, $capacityUsed);
            }

            $occupancy = PremiseOccupancy::create(array_merge($attributes, [
                'company_id' => $premise->company_id,
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'premise_id' => $premise->id,
                'space_id' => $space->id,
                'party_role_id' => $partyRole?->id,
                'party_type' => $partyRole?->party_type ?? ($attributes['party_type'] ?? 'snapshot'),
                'party_id' => $partyRole?->party_id ?? ($attributes['party_id'] ?? null),
                'display_name' => $partyRole?->display_name ?? ($attributes['display_name'] ?? 'Occupant'),
                'occupancy_type' => $attributes['occupancy_type'] ?? 'occupant',
                'status' => $status,
                'start_date' => $attributes['start_date'] ?? ($status === 'active' ? now()->toDateString() : null),
                'capacity_used' => $capacityUsed,
                'is_primary' => (bool) ($attributes['is_primary'] ?? true),
            ]));

            $this->refreshSpaceAvailability($space);
            $this->legacyAgreementSynchronizer->syncFromOccupancy($occupancy);
            event(new PremiseOccupancyCreated($occupancy));

            return $occupancy->fresh(['space', 'partyRole']);
        });
    }

    public function transition(PremiseOccupancy $occupancy, string $toStatus, array $attributes = []): PremiseOccupancy
    {
        return DB::transaction(function () use ($occupancy, $toStatus, $attributes) {
            $occupancy = PremiseOccupancy::withoutGlobalScopes()
                ->where('company_id', $occupancy->company_id)
                ->whereKey($occupancy->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $occupancy->status;
            $this->assertKnownStatus($toStatus);

            if (!OccupancyState::canTransition($previousStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Occupancy cannot move from {$previousStatus} to {$toStatus}.",
                ]);
            }

            $space = PremiseSpace::withoutGlobalScopes()
                ->where('company_id', $occupancy->company_id)
                ->where('premise_id', $occupancy->premise_id)
                ->whereKey($occupancy->space_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!OccupancyState::consumesCapacity($previousStatus) && OccupancyState::consumesCapacity($toStatus)) {
                $this->assertSpaceCanReceiveOccupancy($space);
                $this->assertCapacity($space, (int) $occupancy->capacity_used, $occupancy->id);
            }

            $updates = ['status' => $toStatus];
            foreach (['start_date', 'end_date', 'actual_end_date', 'move_in_status', 'move_out_status', 'notes'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $updates[$field] = $attributes[$field];
                }
            }

            if ($toStatus === 'active' && empty($updates['start_date']) && !$occupancy->start_date) {
                $updates['start_date'] = now()->toDateString();
            }

            if ($toStatus === 'vacated' && empty($updates['actual_end_date'])) {
                $updates['actual_end_date'] = now()->toDateString();
            }

            if ($toStatus === 'cancelled' && OccupancyState::consumesCapacity($previousStatus) && empty($updates['actual_end_date'])) {
                $updates['actual_end_date'] = now()->toDateString();
            }

            $occupancy->update($updates);
            $this->legacyAgreementSynchronizer->syncFromOccupancy($occupancy);
            $this->refreshSpaceAvailability($space);
            event(new PremiseOccupancyStatusChanged($occupancy, $previousStatus));

            return $occupancy->fresh(['space', 'partyRole']);
        });
    }

    public function transfer(
        PremiseOccupancy $source,
        PremiseSpace $destination,
        array $attributes = []
    ): PremiseOccupancy {
        return DB::transaction(function () use ($source, $destination, $attributes) {
            $source = PremiseOccupancy::withoutGlobalScopes()
                ->where('company_id', $source->company_id)
                ->whereKey($source->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!OccupancyState::consumesCapacity($source->status)) {
                throw ValidationException::withMessages([
                    'occupancy' => 'Only a current reservation or occupancy can be transferred.',
                ]);
            }

            $sourceSpace = PremiseSpace::withoutGlobalScopes()
                ->where('company_id', $source->company_id)
                ->where('premise_id', $source->premise_id)
                ->whereKey($source->space_id)
                ->lockForUpdate()
                ->firstOrFail();

            $destination = PremiseSpace::withoutGlobalScopes()
                ->where('company_id', $source->company_id)
                ->where('premise_id', $source->premise_id)
                ->whereKey($destination->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $sourceSpace->id === (int) $destination->id) {
                throw ValidationException::withMessages([
                    'destination_space_id' => 'Choose a different destination space.',
                ]);
            }

            $this->assertSpaceCanReceiveOccupancy($destination);
            $capacityUsed = (int) ($attributes['capacity_used'] ?? $source->capacity_used);
            $this->assertCapacity($destination, $capacityUsed);

            $transferDate = $attributes['start_date'] ?? now()->toDateString();
            $destinationStatus = $attributes['status'] ?? 'active';
            $this->assertKnownStatus($destinationStatus);

            if (!OccupancyState::consumesCapacity($destinationStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'A transfer destination must reserve or occupy capacity.',
                ]);
            }

            $sourcePreviousStatus = $source->status;
            $sourceMetadata = $source->metadata ?? [];
            $source->update([
                'status' => 'vacated',
                'actual_end_date' => $transferDate,
                'move_out_status' => $attributes['source_move_out_status'] ?? 'transferred',
                'metadata' => array_replace($sourceMetadata, [
                    'transfer_to_space_id' => $destination->id,
                ]),
            ]);

            $newOccupancy = PremiseOccupancy::create([
                'company_id' => $source->company_id,
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'premise_id' => $source->premise_id,
                'space_id' => $destination->id,
                'party_role_id' => $source->party_role_id,
                'party_type' => $source->party_type,
                'party_id' => $source->party_id,
                'display_name' => $source->display_name,
                'occupancy_type' => $attributes['occupancy_type'] ?? $source->occupancy_type,
                'status' => $destinationStatus,
                'start_date' => $transferDate,
                'end_date' => $attributes['end_date'] ?? $source->end_date,
                'capacity_used' => $capacityUsed,
                'is_primary' => $source->is_primary,
                'agreement_reference' => $attributes['agreement_reference'] ?? $source->agreement_reference,
                'billing_account_reference' => $attributes['billing_account_reference'] ?? $source->billing_account_reference,
                'deposit_reference' => $attributes['deposit_reference'] ?? $source->deposit_reference,
                'move_in_status' => $attributes['move_in_status'] ?? 'transferred_in',
                'previous_occupancy_id' => $source->id,
                'transfer_reason' => $attributes['transfer_reason'] ?? null,
                'notes' => $attributes['notes'] ?? $source->notes,
                'metadata' => array_replace($sourceMetadata, [
                    'transfer_from_space_id' => $sourceSpace->id,
                ]),
            ]);

            $this->legacyAgreementSynchronizer->syncFromOccupancy($source);
            $this->legacyAgreementSynchronizer->syncFromOccupancy($newOccupancy);
            $this->refreshSpaceAvailability($sourceSpace);
            $this->refreshSpaceAvailability($destination);
            event(new PremiseOccupancyStatusChanged($source, $sourcePreviousStatus));
            event(new PremiseOccupancyCreated($newOccupancy));
            event(new PremiseOccupancyTransferred($source, $newOccupancy));

            return $newOccupancy->fresh(['space', 'partyRole', 'previousOccupancy']);
        });
    }

    private function lockedSpace(Property $premise, PremiseSpace $space): PremiseSpace
    {
        return PremiseSpace::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->whereKey($space->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolvePartyRole(Property $premise, int|string|null $partyRoleId): ?PremisePartyRole
    {
        if (!$partyRoleId) {
            return null;
        }

        return PremisePartyRole::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->whereKey($partyRoleId)
            ->firstOrFail();
    }

    private function assertKnownStatus(string $status): void
    {
        if (!in_array($status, OccupancyState::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown occupancy status.']);
        }
    }

    private function assertSpaceCanReceiveOccupancy(PremiseSpace $space): void
    {
        if (!$space->is_occupiable) {
            throw ValidationException::withMessages([
                'space_id' => 'The selected space is not marked as occupiable.',
            ]);
        }

        if (in_array($space->availability_status, [
            'unavailable', 'maintenance', 'cleaning', 'inspection_hold',
            'compliance_hold', 'owner_hold', 'decommissioned',
        ], true)) {
            throw ValidationException::withMessages([
                'space_id' => 'The selected space is currently unavailable for allocation.',
            ]);
        }
    }

    private function assertCapacity(PremiseSpace $space, int $requested, ?int $excludeOccupancyId = null): void
    {
        $query = PremiseOccupancy::withoutGlobalScopes()
            ->where('company_id', $space->company_id)
            ->where('premise_id', $space->premise_id)
            ->where('space_id', $space->id)
            ->whereIn('status', OccupancyState::CAPACITY_CONSUMING);

        if ($excludeOccupancyId) {
            $query->where('id', '<>', $excludeOccupancyId);
        }

        $used = (int) $query->sum('capacity_used');
        $capacity = $space->capacity === null ? null : (int) $space->capacity;

        if (!OccupancyCapacityPolicy::canAllocate($capacity, $used, $requested)) {
            throw ValidationException::withMessages([
                'capacity_used' => "The requested allocation exceeds the space capacity of {$capacity}.",
            ]);
        }
    }

    private function refreshSpaceAvailability(PremiseSpace $space): void
    {
        if (in_array($space->availability_status, [
            'unavailable', 'maintenance', 'cleaning', 'inspection_hold',
            'compliance_hold', 'owner_hold', 'decommissioned',
        ], true)) {
            return;
        }

        $statuses = PremiseOccupancy::withoutGlobalScopes()
            ->where('company_id', $space->company_id)
            ->where('premise_id', $space->premise_id)
            ->where('space_id', $space->id)
            ->whereIn('status', OccupancyState::CAPACITY_CONSUMING)
            ->pluck('status');

        $availability = 'available';
        if ($statuses->intersect(['active', 'notice_given', 'ending', 'suspended'])->isNotEmpty()) {
            $availability = 'occupied';
        } elseif ($statuses->isNotEmpty()) {
            $availability = 'reserved';
        }

        $space->update(['availability_status' => $availability]);
    }

    private function actorId(): ?int
    {
        if (!function_exists('user')) {
            return null;
        }

        try {
            return user()->id ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
