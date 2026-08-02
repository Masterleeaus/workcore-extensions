<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\{AccommodationActionSupport,AccommodationOccupancyBridge};
use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationReservationState;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CheckInAccommodationStay implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
        private AccommodationOccupancyBridge $occupancies,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $result = $this->db->transaction(function () use ($request): array {
            $reservation = $this->records->reservation($request->companyId, (string) $request->payload['reservation_public_id'], true);
            if ((string) $reservation->status === 'confirmed') {
                AccommodationReservationState::assertTransition('confirmed', 'checked_in');
            } elseif ((string) $reservation->status !== 'checked_in') {
                throw new DomainException('Only confirmed or partially checked-in reservations can check in another space.');
            }

            $spacePublicId = trim((string) ($request->payload['space_public_id'] ?? ''));
            $spaceQuery = $this->db->table('pm_accommodation_reservation_spaces as rs')
                ->join('pm_premise_spaces as s', 's.id', '=', 'rs.space_id')
                ->where('rs.company_id', $request->companyId)
                ->where('rs.reservation_id', (int) $reservation->id)
                ->whereNotIn('rs.status', ['cancelled', 'released']);
            if ($spacePublicId !== '') $spaceQuery->where('s.public_id', $spacePublicId);
            else $spaceQuery->orderByRaw('CASE WHEN s.id = ? THEN 0 ELSE 1 END', [(int) $reservation->primary_space_id]);
            $space = $spaceQuery->lockForUpdate()->first(['s.*']);
            if ($space === null) throw new InvalidArgumentException('Reserved accommodation space was not found.');
            if ($this->db->table('pm_accommodation_stays')
                ->where('company_id', $request->companyId)
                ->where('reservation_id', (int) $reservation->id)
                ->where('space_id', (int) $space->id)
                ->exists()) {
                throw new DomainException('A stay already exists for this reservation space.');
            }

            $latestHousekeeping = $this->db->table('pm_accommodation_housekeeping_tasks')
                ->where('company_id', $request->companyId)->where('space_id', (int) $space->id)
                ->whereNull('deleted_at')->orderByDesc('id')->lockForUpdate()->first();
            if ($latestHousekeeping !== null && ! in_array((string) $latestHousekeeping->status, ['ready', 'cancelled'], true)) {
                $override = (bool) ($request->payload['override_housekeeping'] ?? false);
                $reason = trim((string) ($request->payload['override_reason'] ?? ''));
                if (! $override || $reason === '') {
                    throw new DomainException('The selected space is not housekeeping-ready. An explicit override and reason are required.');
                }
            }

            $assignedGuestIds = $this->db->table('pm_accommodation_stays')
                ->where('company_id', $request->companyId)
                ->where('reservation_id', (int) $reservation->id)
                ->pluck('guest_id')->map(static fn (mixed $id): int => (int) $id)->all();
            $guestQuery = $this->db->table('pm_accommodation_guests')
                ->where('company_id', $request->companyId)->where('reservation_id', (int) $reservation->id)
                ->whereNull('deleted_at');
            $guestPublicId = trim((string) ($request->payload['guest_public_id'] ?? ''));
            if ($guestPublicId !== '') {
                $guestQuery->where('public_id', $guestPublicId);
            } elseif ($assignedGuestIds !== []) {
                $guestQuery->whereNotIn('id', $assignedGuestIds);
            }
            $guestQuery->orderByDesc('is_primary')->orderBy('id');
            $guest = $guestQuery->lockForUpdate()->first();
            if ($guest === null) {
                throw new DomainException($guestPublicId !== ''
                    ? 'The selected reservation guest does not exist or is already assigned to another stay.'
                    : 'An unassigned reservation guest is required before checking in another space.');
            }
            if (in_array((int) $guest->id, $assignedGuestIds, true)) {
                throw new DomainException('The selected reservation guest is already assigned to another stay.');
            }

            $occupancy = $this->occupancies->ensureForReservation($request->companyId, $request->actorId, $reservation, $space, $guest);
            $publicId = (string) Str::ulid();
            $checkedInAt = $request->payload['checked_in_at'] ?? now();
            $stayId = (int) $this->db->table('pm_accommodation_stays')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $request->companyId,
                'reservation_id' => (int) $reservation->id,
                'guest_id' => (int) $guest->id,
                'premise_id' => (int) $reservation->premise_id,
                'space_id' => (int) $space->id,
                'occupancy_id' => $occupancy['occupancy_id'],
                'agreement_id' => $reservation->agreement_id,
                'access_authorisation_id' => $occupancy['access_authorisation_id'],
                'status' => 'in_house',
                'expected_arrival_at' => $reservation->arrival_at,
                'expected_departure_at' => $reservation->departure_at,
                'checked_in_at' => $checkedInAt,
                'checked_in_by_user_id' => $request->actorId,
                'notes' => $request->payload['notes'] ?? null,
                'metadata' => json_encode([
                    'housekeeping_override' => (bool) ($request->payload['override_housekeeping'] ?? false),
                    'housekeeping_override_reason' => $request->payload['override_reason'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->db->table('pm_accommodation_reservations')->where('id', (int) $reservation->id)->update([
                'status' => 'checked_in', 'primary_space_id' => (int) $space->id,
                'occupancy_id' => $occupancy['occupancy_id'], 'updated_at' => now(),
            ]);
            $this->db->table('pm_accommodation_reservation_spaces')->where('reservation_id', (int) $reservation->id)
                ->where('space_id', (int) $space->id)->update(['status' => 'occupied', 'updated_at' => now()]);
            $this->db->table('pm_premise_spaces')->where('id', (int) $space->id)
                ->update(['availability_status' => 'occupied', 'updated_at' => now()]);

            return [
                'public_id' => $publicId,
                'reservation_public_id' => (string) $reservation->public_id,
                'property_public_id' => (string) $this->db->table('pm_properties')->where('id', (int) $reservation->premise_id)->value('public_id'),
                'space_public_id' => (string) $space->public_id,
                'guest_public_id' => (string) $guest->public_id,
                'status' => 'in_house',
                'checked_in_at' => (string) $checkedInAt,
                'expected_departure_at' => (string) $reservation->departure_at,
                'occupancy_linked' => true,
                'stay_id_internal' => $stayId,
            ];
        }, 3);
        unset($result['stay_id_internal']);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('accommodation_stay', (string) $result['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.stay.checked_in', 1, ['stay' => $result])],
        );
    }
}
