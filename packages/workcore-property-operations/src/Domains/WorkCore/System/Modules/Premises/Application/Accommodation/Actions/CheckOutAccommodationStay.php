<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\{AccommodationActionSupport,AccommodationOccupancyBridge};
use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationStayState;
use App\Domains\WorkCore\System\References\TypedReference;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class CheckOutAccommodationStay implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
        private AccommodationOccupancyBridge $occupancies,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $result = $this->db->transaction(function () use ($request): array {
            $stay = $this->records->stay($request->companyId, (string) $request->payload['stay_public_id'], true);
            AccommodationStayState::assertTransition((string) $stay->status, 'checked_out');
            $reservation = $this->db->table('pm_accommodation_reservations')
                ->where('company_id', $request->companyId)->where('id', (int) $stay->reservation_id)
                ->whereNull('deleted_at')->lockForUpdate()->first();
            if ($reservation === null) throw new \DomainException('Stay reservation was not found.');

            $checkedOutAt = $request->payload['checked_out_at'] ?? now();
            $this->db->table('pm_accommodation_stays')->where('id', (int) $stay->id)->update([
                'status' => 'checked_out', 'checked_out_at' => $checkedOutAt,
                'checked_out_by_user_id' => $request->actorId,
                'notes' => $request->payload['notes'] ?? $stay->notes,
                'updated_at' => now(),
            ]);
            $remainingInHouse = $this->db->table('pm_accommodation_stays')
                ->where('company_id', $request->companyId)
                ->where('reservation_id', (int) $reservation->id)
                ->where('id', '!=', (int) $stay->id)
                ->where('status', 'in_house')
                ->exists();
            $reservationStatus = $remainingInHouse ? 'checked_in' : 'checked_out';
            $this->db->table('pm_accommodation_reservations')->where('id', (int) $reservation->id)->update([
                'status' => $reservationStatus, 'updated_at' => now(),
            ]);
            $this->db->table('pm_accommodation_reservation_spaces')->where('reservation_id', (int) $reservation->id)
                ->where('space_id', (int) $stay->space_id)->update(['status' => 'released', 'updated_at' => now()]);
            $this->occupancies->vacateManagedOccupancy(
                $request->companyId,
                $stay->occupancy_id !== null ? (int) $stay->occupancy_id : null,
                $stay->access_authorisation_id !== null ? (int) $stay->access_authorisation_id : null,
            );
            $this->db->table('pm_premise_spaces')->where('company_id', $request->companyId)->where('id', (int) $stay->space_id)
                ->update(['availability_status' => 'cleaning', 'updated_at' => now()]);

            $housekeeping = $this->db->table('pm_accommodation_housekeeping_tasks')
                ->where('company_id', $request->companyId)->where('stay_id', (int) $stay->id)
                ->where('task_type', 'turnover')->whereNull('deleted_at')->first();
            if ($housekeeping === null) {
                $housekeepingPublicId = (string) Str::ulid();
                $this->db->table('pm_accommodation_housekeeping_tasks')->insert([
                    'public_id' => $housekeepingPublicId,
                    'company_id' => $request->companyId,
                    'premise_id' => (int) $stay->premise_id,
                    'space_id' => (int) $stay->space_id,
                    'reservation_id' => (int) $reservation->id,
                    'stay_id' => (int) $stay->id,
                    'task_type' => 'turnover',
                    'status' => 'dirty',
                    'priority' => (string) ($request->payload['housekeeping_priority'] ?? 'normal'),
                    'due_at' => $request->payload['housekeeping_due_at'] ?? now(),
                    'condition_before' => $request->payload['condition'] ?? null,
                    'notes' => $request->payload['housekeeping_notes'] ?? null,
                    'metadata' => json_encode(['generated_by' => 'accommodation_checkout'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $housekeepingPublicId = (string) $housekeeping->public_id;
            }

            return [
                'public_id' => (string) $stay->public_id,
                'reservation_public_id' => (string) $reservation->public_id,
                'status' => 'checked_out',
                'reservation_status' => $reservationStatus,
                'checked_out_at' => (string) $checkedOutAt,
                'housekeeping_task_public_id' => $housekeepingPublicId,
                'space_public_id' => (string) $this->db->table('pm_premise_spaces')->where('id', (int) $stay->space_id)->value('public_id'),
            ];
        }, 3);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('accommodation_stay', (string) $result['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.stay.checked_out', 1, ['stay' => $result])],
        );
    }
}
