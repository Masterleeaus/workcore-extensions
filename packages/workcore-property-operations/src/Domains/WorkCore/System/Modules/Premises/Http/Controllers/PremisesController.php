<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\PremisesResource;
use App\Domains\WorkCore\System\Modules\Premises\Actions\{GetPremisesProfile,GetPremisesReadiness,SearchPremises};
use App\Domains\WorkCore\System\Modules\Premises\Http\Requests\{
    ApprovePremisesRequest,
    ArchivePremisesRequest,
    LinkPremisesContactRequest,
    LinkPremisesDocumentRequest,
    ResolvePremisesHazardRequest,
    StorePremisesAccessPointRequest,
    StorePremisesHazardRequest,
    StorePremisesIdentifierRequest,
    StorePremisesMeterReadingRequest,
    StorePremisesRequest,
    StorePremisesServicePlanRequest,
    StorePremisesServiceWindowRequest,
    StorePremisesSpaceRequest
};
use Illuminate\Http\{JsonResponse,Request};

final class PremisesController
{
    public function index(Request $request, SearchPremises $search, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->paginated(
            $search->execute($request->string('customer_id')->toString() ?: null, $request->string('q')->toString() ?: null, $request->integer('per_page',25)),
            PremisesResource::make(...),
        );
    }

    public function show(string $premises, GetPremisesProfile $profile, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->success($profile->execute($premises));
    }

    public function readiness(string $premises, GetPremisesReadiness $readiness, ApiResponseFactory $responses): JsonResponse
    {
        return $responses->success($readiness->execute($premises));
    }

    public function store(StorePremisesRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.create', $request->validated(), $request, $dispatcher, $responses, true);
    }

    public function storeSpace(string $premises, StorePremisesSpaceRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.space.create', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeHazard(string $premises, StorePremisesHazardRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.hazard.report', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeAccessPoint(string $premises, StorePremisesAccessPointRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.access_point.create', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeServiceWindow(string $premises, StorePremisesServiceWindowRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.service_window.create', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function storeServicePlan(string $premises, StorePremisesServicePlanRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.service_plan.create', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function recordMeterReading(string $premises, StorePremisesMeterReadingRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.meter_reading.record', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function registerIdentifier(string $premises, StorePremisesIdentifierRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.identifier.register', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function linkContact(string $premises, LinkPremisesContactRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.contact.link', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function linkDocument(string $premises, LinkPremisesDocumentRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.document.link', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function resolveHazard(string $premises, string $hazard, ResolvePremisesHazardRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.hazard.resolve', ['premises_public_id'=>$premises,'hazard_public_id'=>$hazard] + $request->validated(), $request, $dispatcher, $responses);
    }

    public function approve(string $premises, ApprovePremisesRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.approve', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses, true);
    }

    public function archive(string $premises, ArchivePremisesRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses): JsonResponse
    {
        return $this->dispatch('workcore.premises.archive', ['premises_public_id'=>$premises] + $request->validated(), $request, $dispatcher, $responses, true);
    }

    private function dispatch(string $action, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $responses, bool $premisesResource = false): JsonResponse
    {
        $tenant=workcore_tenant();
        $key=trim((string)$request->header('Idempotency-Key'));
        abort_if($key==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($action,$payload,$tenant->companyId(),(int)$tenant->userId(),$key,$request->header('X-WorkCore-Confirmation'),'api'));
        $data=$premisesResource ? PremisesResource::make($result->data) : $result->data;
        return $responses->action(new ActionResult($result->actionKey,$data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));
    }
}
