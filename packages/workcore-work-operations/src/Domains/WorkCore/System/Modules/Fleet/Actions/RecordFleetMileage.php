<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Fleet\Contracts\FleetRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class RecordFleetMileage implements BusinessActionHandlerContract
{
    public function __construct(private FleetRepositoryContract $repository){}
    public function handle(ActionRequest $request):ActionHandlerResult
    {
        $id=trim((string)($request->payload['vehicle_public_id']??''));if($id===''){throw new \InvalidArgumentException('vehicle_public_id is required.');}
        $record=$this->repository->recordMileage($id,array_diff_key($request->payload,['vehicle_public_id'=>true]),$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('fleet_mileage',(string)$record['public_id']),[new PendingDomainEvent('workcore.fleet.mileage.recorded',1,['record'=>$record])]);
    }
}
