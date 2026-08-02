<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\CustomerResource;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SearchCustomers;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\StoreCustomerRequest;
use Illuminate\Http\{JsonResponse,Request};

final class CustomerController
{
    public function index(Request $request, SearchCustomers $action, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->paginated(
            $action->execute($request->string('q')->toString() ?: null, $request->integer('per_page', 25)),
            CustomerResource::make(...),
        );
    }

    public function store(StoreCustomerRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            key: 'workcore.customer.create',
            payload: $request->validated(),
            companyId: $tenant->companyId(),
            actorId: (int) $tenant->userId(),
            idempotencyKey: $idempotencyKey,
            confirmationId: $request->header('X-WorkCore-Confirmation'),
            source: 'api',
        ));

        $normalised = new \App\Domains\WorkCore\System\Actions\ActionResult(
            $result->actionKey,
            CustomerResource::make($result->data),
            $result->correlationId,
            $result->auditId,
            $result->eventIds,
            $result->replayed,
        );

        return $responses->action($normalised);
    }
}
