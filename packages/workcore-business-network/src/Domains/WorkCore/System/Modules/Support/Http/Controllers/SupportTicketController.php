<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\SupportTicketResource;
use App\Domains\WorkCore\System\Modules\Support\Actions\SearchSupportTickets;
use App\Domains\WorkCore\System\Modules\Support\Http\Requests\{AssignSupportTicketRequest,ChangeSupportTicketStatusRequest,ConvertSupportTicketToWorkOrderRequest,StoreSupportQueueRequest,StoreSupportTicketMessageRequest,StoreSupportTicketRequest};
use Illuminate\Http\{JsonResponse,Request};
final class SupportTicketController
{
    public function index(Request $request,SearchSupportTickets $search,ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated($search->execute($request->only(['q','status','priority','source','category','queue_public_id','assigned_worker_public_id','premises_public_id','customer_public_id','overdue']),$request->integer('per_page',25)),SupportTicketResource::make(...));
    }
    public function storeQueue(StoreSupportQueueRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.support_queue.create',$request->validated(),$request,$d,$api);}
    public function store(StoreSupportTicketRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.support_ticket.create',$request->validated(),$request,$d,$api);}
    public function message(string $ticket,StoreSupportTicketMessageRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.support_ticket.message.add',['support_ticket_public_id'=>$ticket]+$request->validated(),$request,$d,$api);}
    public function assign(string $ticket,AssignSupportTicketRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.support_ticket.assign',['support_ticket_public_id'=>$ticket]+$request->validated(),$request,$d,$api);}
    public function status(string $ticket,ChangeSupportTicketStatusRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.support_ticket.change_status',['support_ticket_public_id'=>$ticket]+$request->validated(),$request,$d,$api);}
    public function workOrder(string $ticket,ConvertSupportTicketToWorkOrderRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.support_ticket.convert_to_work_order',['support_ticket_public_id'=>$ticket]+$request->validated(),$request,$d,$api);}
    private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api): JsonResponse
    {
        $tenant=workcore_tenant();$idempotency=trim((string)$request->header('Idempotency-Key'));abort_if($idempotency==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($key,$payload,(int)$tenant->companyId(),(int)$tenant->userId(),$idempotency,$request->header('X-WorkCore-Confirmation'),'api'));
        return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));
    }
}
