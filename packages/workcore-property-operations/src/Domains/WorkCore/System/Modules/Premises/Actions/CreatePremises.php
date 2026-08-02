<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Contracts\PremisesRepositoryContract;
use App\Domains\WorkCore\System\Modules\Premises\Data\PremisesData;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreatePremises implements BusinessActionHandlerContract
{
    public function __construct(private PremisesRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->create(PremisesData::fromArray($request->payload),$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('premises',(string)$record['public_id']),[new PendingDomainEvent('workcore.premises.created',1,['premises'=>$record])]);
    }
}
