<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Territories\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\{TerritoryAssignmentResource,TerritoryNodeResource,TerritoryResource};
use App\Domains\WorkCore\System\Modules\Territories\Actions\{MatchTerritories,SearchTerritories};
use App\Domains\WorkCore\System\Modules\Territories\Http\Requests\{AssignTerritoryRequest,StoreBranchRequest,StoreDistrictRequest,StoreRegionRequest,StoreTerritoryRequest};
use Illuminate\Http\{JsonResponse,Request};
final class TerritoryController
{
 public function index(Request $r,SearchTerritories $search,ApiResponseFactory $responses):JsonResponse{return $responses->paginated($search->execute($r->only(['q','status','branch_id','district_id','region_id']),$r->integer('per_page',25)),TerritoryResource::make(...));}
 public function match(Request $r,MatchTerritories $match,ApiResponseFactory $responses):JsonResponse{return $responses->success(array_map(TerritoryResource::make(...),$match->execute($r->string('postcode')->toString()?:null,$r->string('suburb')->toString()?:null,$r->filled('latitude')?(float)$r->input('latitude'):null,$r->filled('longitude')?(float)$r->input('longitude'):null,$r->integer('limit',20))));}
 public function storeRegion(StoreRegionRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.region.create',$r,$d,$a,TerritoryNodeResource::make(...));}
 public function storeDistrict(StoreDistrictRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.district.create',$r,$d,$a,TerritoryNodeResource::make(...));}
 public function storeBranch(StoreBranchRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.branch.create',$r,$d,$a,TerritoryNodeResource::make(...));}
 public function storeTerritory(StoreTerritoryRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.territory.create',$r,$d,$a,TerritoryResource::make(...));}
 public function assign(string $territory,AssignTerritoryRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{$payload=$r->validated();$payload['territory_public_id']=$territory;return $this->dispatchPayload('workcore.territory.assign',$payload,$r,$d,$a,TerritoryAssignmentResource::make(...));}
 private function dispatch(string $key,Request $r,BusinessActionDispatcher $d,ApiResponseFactory $a,callable $resource):JsonResponse{return $this->dispatchPayload($key,$r->validated(),$r,$d,$a,$resource);}
 private function dispatchPayload(string $key,array $payload,Request $r,BusinessActionDispatcher $d,ApiResponseFactory $a,callable $resource):JsonResponse{$t=workcore_tenant();$id=trim((string)$r->header('Idempotency-Key'));abort_if($id==='',422,'Idempotency-Key header is required.');$x=$d->dispatch(new ActionRequest($key,$payload,$t->companyId(),(int)$t->userId(),$id,$r->header('X-WorkCore-Confirmation'),'api'));return $a->action(new ActionResult($x->actionKey,$resource($x->data),$x->correlationId,$x->auditId,$x->eventIds,$x->replayed));}
}
