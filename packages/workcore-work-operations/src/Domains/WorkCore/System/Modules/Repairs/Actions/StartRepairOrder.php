<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Repairs\Contracts\RepairRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class StartRepairOrder implements BusinessActionHandlerContract
{
    public function __construct(private RepairRepositoryContract $repository){}
    public function handle(ActionRequest $request):ActionHandlerResult
    {
        $id=trim((string)($request->payload['repair_public_id']??''));if($id===''){throw new \InvalidArgumentException('repair_public_id is required.');}
        $record=$this->repository->start($id,array_diff_key($request->payload,['repair_public_id'=>true]),$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('repair_order',(string)$record['public_id']),[new PendingDomainEvent('workcore.repairs.order.started',1,['record'=>$record])]);
    }
}
