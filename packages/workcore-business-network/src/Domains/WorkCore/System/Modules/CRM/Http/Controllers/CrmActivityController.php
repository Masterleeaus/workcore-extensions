<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Controllers;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\CrmActivityResource;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ListCrmActivities;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\StoreCrmActivityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CrmActivityController
{
    public function index(Request $request, ListCrmActivities $list, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => ['required', 'in:lead,customer,contact,opportunity'],
            'subject_public_id' => ['required', 'string', 'size:26'],
            'limit' => ['nullable', 'integer', 'between:1,250'],
        ]);
        $rows = $list->execute((string) $validated['subject_type'], (string) $validated['subject_public_id'], (int) ($validated['limit'] ?? 100));
        return $api->success(array_map(CrmActivityResource::make(...), $rows));
    }

    public function store(StoreCrmActivityRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotency === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest('workcore.crm_activity.create', $request->validated(), (int) $tenant->companyId(), (int) $tenant->userId(), $idempotency, $request->header('X-WorkCore-Confirmation'), 'api'));
        return $api->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed));
    }
}
