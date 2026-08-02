<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Modules\Fleet\Actions\SearchFleetVehicles;
use App\Domains\WorkCore\System\Modules\Fleet\Http\Requests\{AssignFleetVehicleRequest,RecordFleetMileageRequest,ReturnFleetVehicleRequest,StoreFleetVehicleRequest};
use Illuminate\Http\{JsonResponse,Request};
final class FleetController
{
    public function index(Request $request,SearchFleetVehicles $search,ApiResponseFactory $api):JsonResponse{return $api->paginated($search->execute($request->only(['q','status']),$request->integer('per_page',25)),static fn(array $row):array=>$row);}
    public function store(StoreFleetVehicleRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.fleet.vehicle.create',$request->validated(),$request,$dispatcher,$api);}
    public function mileage(string $vehicle,RecordFleetMileageRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.fleet.mileage.record',['vehicle_public_id'=>$vehicle]+$request->validated(),$request,$dispatcher,$api);}
    public function assign(string $vehicle,AssignFleetVehicleRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.fleet.vehicle.assign',['vehicle_public_id'=>$vehicle]+$request->validated(),$request,$dispatcher,$api);}
    public function returnVehicle(string $assignment,ReturnFleetVehicleRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.fleet.vehicle.return',['assignment_public_id'=>$assignment]+$request->validated(),$request,$dispatcher,$api);}
    private function dispatch(string $action,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{$tenant=workcore_tenant();$id=trim((string)$request->header('Idempotency-Key'));abort_if($id==='',422,'Idempotency-Key header is required.');$result=$dispatcher->dispatch(new ActionRequest($action,$payload,$tenant->companyId(),(int)$tenant->userId(),$id,$request->header('X-WorkCore-Confirmation'),'api'));return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));}
}
