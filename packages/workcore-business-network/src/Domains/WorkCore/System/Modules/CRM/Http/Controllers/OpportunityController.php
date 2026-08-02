<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Controllers;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\OpportunityResource;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SearchOpportunities;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\MarkOpportunityLostRequest;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\MoveOpportunityRequest;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\StoreOpportunityRequest;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\UpdateOpportunityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OpportunityController
{
    public function index(Request $request, SearchOpportunities $search, ApiResponseFactory $api): JsonResponse
    {
        $filters = $request->only(['q', 'status', 'currency_code', 'pipeline_public_id', 'stage_public_id', 'owner_user_id', 'overdue']);
        return $api->paginated($search->execute($filters, $request->integer('per_page', 25)), OpportunityResource::make(...));
    }

    public function store(StoreOpportunityRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.opportunity.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function update(string $opportunity, UpdateOpportunityRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.opportunity.update', ['opportunity_public_id' => $opportunity] + $request->validated(), $request, $dispatcher, $api, 200);
    }

    public function move(string $opportunity, MoveOpportunityRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.opportunity.move', ['opportunity_public_id' => $opportunity] + $request->validated(), $request, $dispatcher, $api, 200);
    }

    public function won(string $opportunity, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.opportunity.mark_won', ['opportunity_public_id' => $opportunity], $request, $dispatcher, $api, 200);
    }

    public function lost(string $opportunity, MarkOpportunityLostRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.opportunity.mark_lost', ['opportunity_public_id' => $opportunity] + $request->validated(), $request, $dispatcher, $api, 200);
    }

    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api, int $status = 201): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotency === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest($key, $payload, (int) $tenant->companyId(), (int) $tenant->userId(), $idempotency, $request->header('X-WorkCore-Confirmation'), 'api'));
        return $api->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed), $status);
    }
}
