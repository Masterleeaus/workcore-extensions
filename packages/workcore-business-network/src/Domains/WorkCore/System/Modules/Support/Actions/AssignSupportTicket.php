<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Support\Contracts\SupportTicketRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class AssignSupportTicket implements BusinessActionHandlerContract
{
    public function __construct(private SupportTicketRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $ticket=(string)$request->payload['support_ticket_public_id'];
        $record=$this->repository->assign($ticket,$request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('support_ticket',$ticket),[new PendingDomainEvent('workcore.support_ticket.assigned',1,['support_ticket'=>$record])]);
    }
}
