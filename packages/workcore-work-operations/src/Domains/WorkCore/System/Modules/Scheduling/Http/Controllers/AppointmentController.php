<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Http\Controllers;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\AppointmentResource;
use App\Domains\WorkCore\System\Modules\Scheduling\Actions\SearchAppointments;
use App\Domains\WorkCore\System\Modules\Scheduling\Http\Requests\ChangeAppointmentStatusRequest;
use App\Domains\WorkCore\System\Modules\Scheduling\Http\Requests\RescheduleAppointmentRequest;
use App\Domains\WorkCore\System\Modules\Scheduling\Http\Requests\StoreAppointmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentController
{
    public function index(Request $request, SearchAppointments $search, ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated(
            $search->execute(
                query: $request->string('q')->toString() ?: null,
                status: $request->string('status')->toString() ?: null,
                appointmentType: $request->string('appointment_type')->toString() ?: null,
                customerPublicId: $request->string('customer_id')->toString() ?: null,
                premisesPublicId: $request->string('premises_id')->toString() ?: null,
                workOrderPublicId: $request->string('work_order_id')->toString() ?: null,
                startsFrom: $request->string('starts_from')->toString() ?: null,
                startsTo: $request->string('starts_to')->toString() ?: null,
                perPage: $request->integer('per_page', 25),
            ),
            AppointmentResource::make(...),
        );
    }

    public function store(StoreAppointmentRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.appointment.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function reschedule(
        string $appointment,
        RescheduleAppointmentRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.appointment.reschedule',
            ['appointment_public_id' => $appointment] + $request->validated(),
            $request,
            $dispatcher,
            $api,
        );
    }

    public function changeStatus(
        string $appointment,
        ChangeAppointmentStatusRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.appointment.change_status',
            ['appointment_public_id' => $appointment] + $request->validated(),
            $request,
            $dispatcher,
            $api,
        );
    }

    /** @param array<string,mixed> $payload */
    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            key: $key,
            payload: $payload,
            companyId: (int) $tenant->companyId(),
            actorId: (int) $tenant->userId(),
            idempotencyKey: $idempotencyKey,
            confirmationId: $request->header('X-WorkCore-Confirmation'),
            source: 'api',
        ));

        return $api->action(new ActionResult(
            actionKey: $result->actionKey,
            data: $result->data,
            correlationId: $result->correlationId,
            auditId: $result->auditId,
            eventIds: $result->eventIds,
            replayed: $result->replayed,
        ));
    }
}
