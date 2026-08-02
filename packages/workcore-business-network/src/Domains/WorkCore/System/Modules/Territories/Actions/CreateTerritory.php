<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Territories\Contracts\TerritoryRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateTerritory implements BusinessActionHandlerContract
{
    public function __construct(private TerritoryRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->createTerritory($request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('territory',(string)$record['public_id']),[new PendingDomainEvent('workcore.territory.created',1,['record'=>$record])]);
    }
}
