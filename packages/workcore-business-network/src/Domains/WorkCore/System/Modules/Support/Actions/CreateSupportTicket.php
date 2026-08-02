<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Support\Contracts\SupportTicketRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateSupportTicket implements BusinessActionHandlerContract
{
    public function __construct(private SupportTicketRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->createTicket($request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('support_ticket',(string)$record['public_id']),[new PendingDomainEvent('workcore.support_ticket.created',1,['support_ticket'=>$record])]);
    }
}
