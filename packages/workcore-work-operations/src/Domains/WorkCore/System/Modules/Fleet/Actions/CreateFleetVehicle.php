<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Fleet\Contracts\FleetRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateFleetVehicle implements BusinessActionHandlerContract
{
    public function __construct(private FleetRepositoryContract $repository){}
    public function handle(ActionRequest $request):ActionHandlerResult
    {
        $record=$this->repository->createVehicle($request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('fleet_vehicle',(string)$record['public_id']),[new PendingDomainEvent('workcore.fleet.vehicle.created',1,['record'=>$record])]);
    }
}
