<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Http\Requests\{
    AddAccommodationChargeRequest,
    ChangeAccommodationReservationStatusRequest,
    CheckInAccommodationStayRequest,
    CheckOutAccommodationStayRequest,
    CreateAccommodationReservationRequest,
    UpdateAccommodationHousekeepingRequest,
    UpsertAccommodationRatePlanRequest
};
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\ReadModels\{
    GetAccommodationBoard,
    GetAccommodationFolio,
    SearchAccommodationAvailability
};
use Illuminate\Http\{JsonResponse,Request};

final class AccommodationManagementController
{
    public function availability(Request $request, SearchAccommodationAvailability $read, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'arrival_at' => ['required', 'date'],
            'departure_at' => ['required', 'date', 'after:arrival_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'property_public_id' => ['nullable', 'string', 'size:26'],
        ]);
        return $api->success($read->execute(
            (string) $validated['arrival_at'],
            (string) $validated['departure_at'],
            (int) ($validated['capacity'] ?? 1),
            $validated['property_public_id'] ?? null,
        ));
    }

    public function board(Request $request, GetAccommodationBoard $read, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'property_public_id' => ['nullable', 'string', 'size:26'],
        ]);
        return $api->success($read->execute(
            (string) $validated['from'],
            (string) $validated['to'],
            $validated['property_public_id'] ?? null,
        ));
    }

    public function folio(string $reservation, GetAccommodationFolio $read, ApiResponseFactory $api): JsonResponse
    {
        return $api->success($read->execute($reservation));
    }

    public function upsertRatePlan(
        ?string $ratePlan,
        UpsertAccommodationRatePlanRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        $payload = $request->validated();
        if ($ratePlan !== null && $ratePlan !== '') {
            $payload = ['rate_plan_public_id' => $ratePlan] + $payload;
        }
        return $this->dispatch('workcore.accommodation.rate_plan.upsert', $payload, $request, $dispatcher, $api);
    }

    public function createReservation(
        CreateAccommodationReservationRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.accommodation.reservation.create', $request->validated(), $request, $dispatcher, $api, 201);
    }

    public function changeReservationStatus(
        string $reservation,
        ChangeAccommodationReservationStatusRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.accommodation.reservation.status',
            ['reservation_public_id' => $reservation] + $request->validated(),
            $request, $dispatcher, $api,
        );
    }

    public function checkIn(
        string $reservation,
        CheckInAccommodationStayRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.accommodation.stay.check_in',
            ['reservation_public_id' => $reservation] + $request->validated(),
            $request, $dispatcher, $api,
        );
    }

    public function checkOut(
        string $stay,
        CheckOutAccommodationStayRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.accommodation.stay.check_out',
            ['stay_public_id' => $stay] + $request->validated(),
            $request, $dispatcher, $api,
        );
    }

    public function updateHousekeeping(
        string $task,
        UpdateAccommodationHousekeepingRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.accommodation.housekeeping.update',
            ['task_public_id' => $task] + $request->validated(),
            $request, $dispatcher, $api,
        );
    }

    public function addCharge(
        string $reservation,
        AddAccommodationChargeRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.accommodation.charge.add',
            ['reservation_public_id' => $reservation] + $request->validated(),
            $request, $dispatcher, $api,
        );
    }

    /** @param array<string,mixed> $payload */
    private function dispatch(
        string $key,
        array $payload,
        Request $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
        int $status = 200,
    ): JsonResponse {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            $key,
            $payload,
            (int) $tenant->companyId(),
            (int) $tenant->userId(),
            $idempotencyKey,
            $request->header('X-WorkCore-Confirmation'),
            'api',
        ));

        return $api->action(new ActionResult(
            $result->actionKey,
            $result->data,
            $result->correlationId,
            $result->auditId,
            $result->eventIds,
            $result->replayed,
        ), $status);
    }
}
