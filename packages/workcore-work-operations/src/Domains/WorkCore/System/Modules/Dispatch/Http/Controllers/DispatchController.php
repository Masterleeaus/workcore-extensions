<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\{ApiResponseFactory};
use App\Domains\WorkCore\System\Api\Resources\DispatchAssignmentResource;
use App\Domains\WorkCore\System\Modules\Dispatch\Actions\SearchDispatchBoard;
use App\Domains\WorkCore\System\Modules\Dispatch\Http\Requests\{ChangeDispatchStatusRequest,ReassignDispatchAssignmentRequest,StoreDispatchAssignmentRequest};
use Illuminate\Http\{JsonResponse,Request};

final class DispatchController
{
    public function index(Request $request, SearchDispatchBoard $search, ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated($search->execute(
            query: $request->string('q')->toString() ?: null,
            status: $request->string('status')->toString() ?: null,
            assignmentType: $request->string('assignment_type')->toString() ?: null,
            assignedUserId: $request->integer('assigned_user_id') ?: null,
            premisesPublicId: $request->string('premises_id')->toString() ?: null,
            scheduledFrom: $request->string('scheduled_from')->toString() ?: null,
            scheduledTo: $request->string('scheduled_to')->toString() ?: null,
            perPage: $request->integer('per_page', 25),
        ), DispatchAssignmentResource::make(...));
    }

    public function store(StoreDispatchAssignmentRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.dispatch.assign', $request->validated(), $request, $dispatcher, $api);
    }

    public function reassign(string $dispatch, ReassignDispatchAssignmentRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.dispatch.reassign', ['dispatch_public_id' => $dispatch] + $request->validated(), $request, $dispatcher, $api);
    }

    public function changeStatus(string $dispatch, ChangeDispatchStatusRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.dispatch.change_status', ['dispatch_public_id' => $dispatch] + $request->validated(), $request, $dispatcher, $api);
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
        return $api->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed));
    }
}
