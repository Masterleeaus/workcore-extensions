<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Controllers;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\SalesPipelineResource;
use App\Domains\WorkCore\System\Modules\CRM\Actions\GetCrmForecast;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ListSalesPipelines;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ViewSalesPipelineBoard;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\StoreSalesPipelineRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SalesPipelineController
{
    public function index(ListSalesPipelines $list, ApiResponseFactory $api): JsonResponse
    {
        return $api->success(array_map(SalesPipelineResource::make(...), $list->execute()));
    }

    public function store(StoreSalesPipelineRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.sales_pipeline.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function board(string $pipeline, ViewSalesPipelineBoard $board, ApiResponseFactory $api): JsonResponse
    {
        return $api->success(SalesPipelineResource::make($board->execute($pipeline)));
    }

    public function forecast(Request $request, GetCrmForecast $forecast, ApiResponseFactory $api): JsonResponse
    {
        $pipeline = trim((string) $request->query('pipeline_public_id'));
        return $api->success($forecast->execute($pipeline === '' ? null : $pipeline));
    }

    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotency === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest($key, $payload, (int) $tenant->companyId(), (int) $tenant->userId(), $idempotency, $request->header('X-WorkCore-Confirmation'), 'api'));
        return $api->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed));
    }
}
