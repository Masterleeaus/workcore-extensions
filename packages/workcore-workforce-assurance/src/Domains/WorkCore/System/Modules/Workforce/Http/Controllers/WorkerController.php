<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\WorkerResource;
use App\Domains\WorkCore\System\Modules\Workforce\Actions\SearchWorkers;
use App\Domains\WorkCore\System\Modules\Workforce\Http\Requests\{AssignWorkerSkillRequest,StoreCertificationRequest,StoreSkillRequest,StoreWorkerRequest};
use Illuminate\Http\{JsonResponse,Request};
final class WorkerController
{
 public function index(Request $request,SearchWorkers $search,ApiResponseFactory $api):JsonResponse{return $api->paginated($search->execute($request->string('q')->toString()?:null,$request->string('status')->toString()?:null,$request->string('skill_id')->toString()?:null,$request->integer('per_page',25)),WorkerResource::make(...));}
 public function store(StoreWorkerRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.worker.create',$request->validated(),$request,$d,$a);}
 public function storeSkill(StoreSkillRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.skill.create',$request->validated(),$request,$d,$a);}
 public function assignSkill(string $worker,AssignWorkerSkillRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.worker.assign_skill',['worker_public_id'=>$worker]+$request->validated(),$request,$d,$a);}
 public function addCertification(string $worker,StoreCertificationRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{return $this->dispatch('workcore.worker.add_certification',['worker_public_id'=>$worker]+$request->validated(),$request,$d,$a);}
 private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $d,ApiResponseFactory $a):JsonResponse{$t=workcore_tenant();$i=trim((string)$request->header('Idempotency-Key'));abort_if($i==='',422,'Idempotency-Key header is required.');$r=$d->dispatch(new ActionRequest($key,$payload,(int)$t->companyId(),(int)$t->userId(),$i,$request->header('X-WorkCore-Confirmation'),'api'));return $a->action(new ActionResult($r->actionKey,$r->data,$r->correlationId,$r->auditId,$r->eventIds,$r->replayed));}
}
