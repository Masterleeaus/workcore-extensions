<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Workforce\Contracts\WorkerRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class AssignWorkerSkill implements BusinessActionHandlerContract
{
 public function __construct(private WorkerRepositoryContract $repository){}
 public function handle(ActionRequest $request): ActionHandlerResult
 {
  $record=$this->repository->assignSkill((string)$request->payload['worker_public_id'],$request->payload,$request->companyId,$request->actorId);
  return new ActionHandlerResult(data:$record,aggregate:new TypedReference('worker_skill',(string)$record['public_id']),events:[new PendingDomainEvent('workcore.worker.skill_assigned',1,['record'=>$record])]);
 }
}
