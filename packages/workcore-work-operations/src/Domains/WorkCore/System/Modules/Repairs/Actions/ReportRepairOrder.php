<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Repairs\Contracts\RepairRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class ReportRepairOrder implements BusinessActionHandlerContract
{
    public function __construct(private RepairRepositoryContract $repository){}
    public function handle(ActionRequest $request):ActionHandlerResult
    {
        $record=$this->repository->report($request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('repair_order',(string)$record['public_id']),[new PendingDomainEvent('workcore.repairs.order.reported',1,['record'=>$record])]);
    }
}
