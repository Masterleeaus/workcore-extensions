<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Modules\Repairs\Actions\SearchRepairOrders;
use App\Domains\WorkCore\System\Modules\Repairs\Http\Requests\{CompleteRepairOrderRequest,ReportRepairOrderRequest,StartRepairOrderRequest,StoreRepairTemplateRequest};
use Illuminate\Http\{JsonResponse,Request};
final class RepairController
{
    public function index(Request $request,SearchRepairOrders $search,ApiResponseFactory $api):JsonResponse{return $api->paginated($search->execute($request->only(['status','asset_public_id']),$request->integer('per_page',25)),static fn(array $row):array=>$row);}
    public function createTemplate(StoreRepairTemplateRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.repairs.template.create',$request->validated(),$request,$dispatcher,$api);}
    public function report(ReportRepairOrderRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.repairs.order.report',$request->validated(),$request,$dispatcher,$api);}
    public function start(string $repair,StartRepairOrderRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.repairs.order.start',['repair_public_id'=>$repair]+$request->validated(),$request,$dispatcher,$api);}
    public function complete(string $repair,CompleteRepairOrderRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.repairs.order.complete',['repair_public_id'=>$repair]+$request->validated(),$request,$dispatcher,$api);}
    private function dispatch(string $action,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{$tenant=workcore_tenant();$id=trim((string)$request->header('Idempotency-Key'));abort_if($id==='',422,'Idempotency-Key header is required.');$result=$dispatcher->dispatch(new ActionRequest($action,$payload,$tenant->companyId(),(int)$tenant->userId(),$id,$request->header('X-WorkCore-Confirmation'),'api'));return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));}
}
