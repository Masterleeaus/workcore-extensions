<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\{AccommodationActionSupport,AccommodationAvailabilityService};
use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationReservationState;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;

final class ChangeAccommodationReservationStatus implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
        private AccommodationAvailabilityService $availability,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $target = (string) $request->payload['status'];
        if (in_array($target, ['checked_in', 'checked_out'], true)) {
            throw new DomainException('Use the accommodation check-in or check-out action for stay transitions.');
        }

        $record = $this->db->transaction(function () use ($request, $target): array {
            $reservation = $this->records->reservation($request->companyId, (string) $request->payload['reservation_public_id'], true);
            AccommodationReservationState::assertTransition((string) $reservation->status, $target);

            if (AccommodationReservationState::isCapacityConsuming($target)) {
                $propertyPublicId = (string) $this->db->table('pm_properties')
                    ->where('company_id', $request->companyId)->where('id', (int) $reservation->premise_id)->value('public_id');
                $spacePublicIds = $this->db->table('pm_accommodation_reservation_spaces as rs')
                    ->join('pm_premise_spaces as s', 's.id', '=', 'rs.space_id')
                    ->where('rs.company_id', $request->companyId)->where('rs.reservation_id', (int) $reservation->id)
                    ->pluck('s.public_id')->map(static fn ($id): string => (string) $id)->all();
                $this->availability->assertSpacesAvailable(
                    $request->companyId,
                    $propertyPublicId,
                    $spacePublicIds,
                    (string) $reservation->arrival_at,
                    (string) $reservation->departure_at,
                    1,
                    (string) $reservation->public_id,
                );
            }

            $updates = ['status' => $target, 'updated_at' => now()];
            if ($target === 'confirmed') $updates['confirmed_at'] = now();
            if ($target === 'cancelled') {
                $updates['cancelled_at'] = now();
                $updates['cancellation_reason'] = trim((string) ($request->payload['reason'] ?? '')) ?: null;
            }
            $this->db->table('pm_accommodation_reservations')->where('id', (int) $reservation->id)->update($updates);
            if (in_array($target, ['cancelled', 'no_show', 'expired'], true)) {
                $this->db->table('pm_accommodation_reservation_spaces')->where('reservation_id', (int) $reservation->id)
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
            } elseif (in_array($target, ['tentative', 'confirmed'], true)) {
                $this->db->table('pm_accommodation_reservation_spaces')->where('reservation_id', (int) $reservation->id)
                    ->update(['status' => 'reserved', 'updated_at' => now()]);
            }

            $fresh = (array) $this->records->reservation($request->companyId, (string) $reservation->public_id);
            foreach (['id','company_id','user_id','premise_id','primary_space_id','occupancy_id','agreement_id','business_line_id','rate_plan_id'] as $field) unset($fresh[$field]);
            $fresh['metadata'] = $this->records->decode($fresh['metadata'] ?? null);
            return $fresh;
        }, 3);

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('accommodation_reservation', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.reservation.status_changed', 1, [
                'reservation' => $record,
            ])],
        );
    }
}
