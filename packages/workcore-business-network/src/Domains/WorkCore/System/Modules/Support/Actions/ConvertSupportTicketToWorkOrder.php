<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\Modules\Support\Contracts\SupportTicketRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;
final class ConvertSupportTicketToWorkOrder implements BusinessActionHandlerContract
{
    public function __construct(private SupportTicketRepositoryContract $tickets,private WorkOrderRepositoryContract $workOrders) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $ticketId=(string)$request->payload['support_ticket_public_id'];
        $ticket=$this->tickets->findByPublicId($request->companyId,$ticketId);
        if(!$ticket) throw new InvalidArgumentException('Support ticket does not belong to the active company.');
        if(!empty($ticket['work_order_public_id'])) return new ActionHandlerResult($ticket,new TypedReference('support_ticket',$ticketId),[]);
        if(empty($ticket['customer_public_id'])) throw new InvalidArgumentException('A customer must be linked before converting a support ticket to a Work Order.');
        $priority=match((string)$ticket['priority']){'emergency','urgent'=>'urgent','high'=>'high','low'=>'low',default=>'normal'};
        $workOrder=$this->workOrders->create([
            'customer_public_id'=>$ticket['customer_public_id'],
            'premises_public_id'=>$ticket['premises_public_id']??null,
            'title'=>$request->payload['title']??("Support {$ticket['ticket_number']}: {$ticket['subject']}"),
            'description'=>$request->payload['description']??$ticket['description'],
            'priority'=>$priority,
            'status'=>$request->payload['status']??'ready',
            'source'=>'support_ticket',
            'due_at'=>$request->payload['due_at']??null,
            'services'=>$request->payload['services']??[],
        ],$request->companyId,$request->actorId);
        $linked=$this->tickets->linkWorkOrder($ticketId,(string)$workOrder['public_id'],$request->companyId,$request->actorId);
        return new ActionHandlerResult($linked,new TypedReference('support_ticket',$ticketId),[
            new PendingDomainEvent('workcore.support_ticket.converted_to_work_order',1,['support_ticket'=>$linked,'work_order'=>$workOrder]),
            new PendingDomainEvent('workcore.work_order.created',1,['work_order'=>$workOrder,'source_support_ticket_public_id'=>$ticketId]),
        ]);
    }
}
