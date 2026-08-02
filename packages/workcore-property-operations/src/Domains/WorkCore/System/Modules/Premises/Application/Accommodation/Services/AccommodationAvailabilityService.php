<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services;

use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationDateWindow;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationReservationState;
use DomainException;
use Illuminate\Database\ConnectionInterface;

final class AccommodationAvailabilityService
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return list<array<string,mixed>> */
    public function availableSpaces(
        int $companyId,
        string $arrivalAt,
        string $departureAt,
        int $capacity = 1,
        ?string $premisePublicId = null,
        ?string $excludeReservationPublicId = null,
    ): array {
        AccommodationDateWindow::overlaps($arrivalAt, $departureAt, $arrivalAt, $departureAt);
        if ($capacity < 1) {
            throw new DomainException('Accommodation capacity must be at least one.');
        }

        $arrivalDate = substr($arrivalAt, 0, 10);
        $departureDate = substr($departureAt, 0, 10);
        $consuming = AccommodationReservationState::CAPACITY_CONSUMING;

        $query = $this->db->table('pm_premise_spaces as s')
            ->join('pm_properties as p', function ($join): void {
                $join->on('p.id', '=', 's.premise_id')->on('p.company_id', '=', 's.company_id');
            })
            ->where('s.company_id', $companyId)
            ->where('s.status', 'active')
            ->where('s.is_occupiable', true)
            ->where('s.is_bookable', true)
            ->whereNotIn('s.availability_status', [
                'unavailable', 'maintenance', 'inspection_hold', 'compliance_hold', 'owner_hold', 'decommissioned',
            ])
            ->whereRaw('COALESCE(s.capacity, 1) >= ?', [$capacity])
            ->when($premisePublicId, fn ($builder) => $builder->where('p.public_id', $premisePublicId))
            ->whereNotExists(function ($sub) use ($companyId, $arrivalAt, $departureAt, $consuming, $excludeReservationPublicId): void {
                $sub->selectRaw('1')
                    ->from('pm_accommodation_reservation_spaces as rs')
                    ->join('pm_accommodation_reservations as r', function ($join): void {
                        $join->on('r.id', '=', 'rs.reservation_id')->on('r.company_id', '=', 'rs.company_id');
                    })
                    ->whereColumn('rs.space_id', 's.id')
                    ->where('rs.company_id', $companyId)
                    ->whereNull('r.deleted_at')
                    ->whereIn('r.status', $consuming)
                    ->whereNotIn('rs.status', ['cancelled', 'released'])
                    ->where('rs.arrival_at', '<', $departureAt)
                    ->where('rs.departure_at', '>', $arrivalAt)
                    ->when($excludeReservationPublicId, fn ($q) => $q->where('r.public_id', '!=', $excludeReservationPublicId));
            })
            ->whereNotExists(function ($sub) use ($companyId, $arrivalDate, $departureDate): void {
                $sub->selectRaw('1')
                    ->from('pm_premise_occupancies as o')
                    ->whereColumn('o.space_id', 's.id')
                    ->where('o.company_id', $companyId)
                    ->whereIn('o.status', ['reserved', 'pending', 'active', 'notice_given', 'ending', 'suspended'])
                    ->where(function ($date) use ($departureDate): void {
                        $date->whereNull('o.start_date')->orWhere('o.start_date', '<', $departureDate);
                    })
                    ->where(function ($date) use ($arrivalDate): void {
                        $date->whereNull('o.actual_end_date')
                            ->where(function ($end) use ($arrivalDate): void {
                                $end->whereNull('o.end_date')->orWhere('o.end_date', '>', $arrivalDate);
                            });
                    });
            });

        return $query->orderBy('p.name')->orderBy('s.name')->limit(500)->get([
            'p.public_id as premise_public_id', 'p.name as premise_name',
            's.public_id as space_public_id', 's.code as space_code', 's.name as space_name',
            's.space_type', 's.capacity', 's.availability_status', 's.floor', 's.zone',
        ])->map(static fn (object $row): array => (array) $row)->all();
    }

    /** @param list<string> $spacePublicIds @return list<array<string,mixed>> */
    public function assertSpacesAvailable(
        int $companyId,
        string $premisePublicId,
        array $spacePublicIds,
        string $arrivalAt,
        string $departureAt,
        int $capacity,
        ?string $excludeReservationPublicId = null,
    ): array {
        $available = $this->availableSpaces(
            $companyId, $arrivalAt, $departureAt, $capacity, $premisePublicId, $excludeReservationPublicId,
        );
        $indexed = [];
        foreach ($available as $space) {
            $indexed[(string) $space['space_public_id']] = $space;
        }
        $selected = [];
        foreach (array_values(array_unique($spacePublicIds)) as $publicId) {
            if (! isset($indexed[$publicId])) {
                throw new DomainException("Accommodation space [{$publicId}] is unavailable for the requested window.");
            }
            $selected[] = $indexed[$publicId];
        }
        if ($selected === []) {
            throw new DomainException('At least one accommodation space is required.');
        }

        return $selected;
    }
}
