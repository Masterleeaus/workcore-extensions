<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\CRM\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\LeadResource;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SearchLeads;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\{StoreLeadRequest,UpdateLeadRequest};
use App\Domains\WorkCore\System\Modules\CRM\Services\LeadLifecycleService;
use Illuminate\Http\{JsonResponse,Request};
final class LeadController
{
    public function index(Request $request,SearchLeads $action,ApiResponseFactory $responses): JsonResponse{return $responses->paginated($action->execute($request->string('q')->toString()?:null,$request->integer('per_page',25)),LeadResource::make(...));}
    public function options(LeadLifecycleService $service,ApiResponseFactory $responses): JsonResponse{return $responses->success($service->options(workcore_tenant()->companyId()));}
    public function store(StoreLeadRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses): JsonResponse{return $this->dispatch($request,$dispatcher,$responses,'workcore.lead.create',$request->validated());}
    public function update(UpdateLeadRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses,string $lead): JsonResponse{return $this->dispatch($request,$dispatcher,$responses,'workcore.lead.update',['lead_public_id'=>$lead]+$request->validated());}
    public function convert(Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses,string $lead): JsonResponse{return $this->dispatch($request,$dispatcher,$responses,'workcore.lead.convert',['lead_public_id'=>$lead]);}
    private function dispatch(Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses,string $key,array $payload): JsonResponse
    {
        $tenant=workcore_tenant();$id=trim((string)$request->header('Idempotency-Key'));abort_if($id==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($key,$payload,$tenant->companyId(),(int)$tenant->userId(),$id,$request->header('X-WorkCore-Confirmation'),'api'));
        return $responses->action(new \App\Domains\WorkCore\System\Actions\ActionResult($result->actionKey,LeadResource::make($result->data),$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));
    }
}
