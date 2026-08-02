<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\ContactRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\ContactData;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateContact implements BusinessActionHandlerContract
{
    public function __construct(private ContactRepositoryContract $repo) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repo->create(ContactData::fromArray($request->payload),$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('contact',(string)$record['public_id']),[new PendingDomainEvent('workcore.contact.created',1,['contact'=>$record])]);
    }
}
