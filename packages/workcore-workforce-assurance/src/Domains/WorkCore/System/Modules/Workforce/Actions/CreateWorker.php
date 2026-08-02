<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Workforce\Contracts\WorkerRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateWorker implements BusinessActionHandlerContract
{
 public function __construct(private WorkerRepositoryContract $repository){}
 public function handle(ActionRequest $request): ActionHandlerResult
 {
  $record=$this->repository->createWorker($request->payload,$request->companyId,$request->actorId);
  return new ActionHandlerResult(data:$record,aggregate:new TypedReference('worker',(string)$record['public_id']),events:[new PendingDomainEvent('workcore.worker.created',1,['worker'=>$record])]);
 }
}
