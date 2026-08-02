<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Http\Controllers;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\WorkOrderResource;
use App\Domains\WorkCore\System\Modules\Operations\Actions\SearchWorkOrders;
use App\Domains\WorkCore\System\Modules\Operations\Http\Requests\ChangeWorkOrderStatusRequest;
use App\Domains\WorkCore\System\Modules\Operations\Http\Requests\StoreTimeEntryRequest;
use App\Domains\WorkCore\System\Modules\Operations\Http\Requests\StoreWorkOrderRequest;
use App\Domains\WorkCore\System\Modules\Operations\Http\Requests\UpdateWorkOrderTaskStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkOrderController
{
    public function index(Request $request, SearchWorkOrders $search, ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated(
            $search->execute(
                query: $request->string('q')->toString() ?: null,
                status: $request->string('status')->toString() ?: null,
                customerPublicId: $request->string('customer_id')->toString() ?: null,
                premisesPublicId: $request->string('premises_id')->toString() ?: null,
                businessLinePublicId: $request->string('business_line_id')->toString() ?: null,
                perPage: $request->integer('per_page', 25),
            ),
            WorkOrderResource::make(...),
        );
    }

    public function store(StoreWorkOrderRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.work_order.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function changeStatus(
        string $workOrder,
        ChangeWorkOrderStatusRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.work_order.change_status',
            ['work_order_public_id' => $workOrder] + $request->validated(),
            $request,
            $dispatcher,
            $api,
        );
    }

    public function updateTaskStatus(
        string $task,
        UpdateWorkOrderTaskStatusRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.work_order_task.update_status',
            ['task_public_id' => $task] + $request->validated(),
            $request,
            $dispatcher,
            $api,
        );
    }

    public function addTimeEntry(
        string $workOrder,
        StoreTimeEntryRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.time_entry.add',
            ['work_order_public_id' => $workOrder] + $request->validated(),
            $request,
            $dispatcher,
            $api,
        );
    }

    /** @param array<string,mixed> $payload */
    private function dispatch(
        string $actionKey,
        array $payload,
        Request $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');

        $result = $dispatcher->dispatch(new ActionRequest(
            key: $actionKey,
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
