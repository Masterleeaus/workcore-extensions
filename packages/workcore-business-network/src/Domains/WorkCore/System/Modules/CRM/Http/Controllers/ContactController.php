<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\CRM\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\{ApiResponseFactory};
use App\Domains\WorkCore\System\Api\Resources\ContactResource;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\ContactRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Http\Requests\StoreContactRequest;
use Illuminate\Http\{JsonResponse,Request};
final class ContactController
{
    public function index(Request $request, ContactRepositoryContract $repo, ApiResponseFactory $responses): JsonResponse
    {
        $items=array_map(ContactResource::make(...),$repo->listForCustomer(workcore_tenant()->companyId(),(string)$request->query('customer_public_id')));
        return $responses->success($items);
    }
    public function store(StoreContactRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses): JsonResponse{return $this->dispatch($request,$dispatcher,$responses,'workcore.contact.create',$request->validated());}
    public function setPrimary(Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses,string $contact): JsonResponse{return $this->dispatch($request,$dispatcher,$responses,'workcore.contact.set_primary',['contact_public_id'=>$contact]);}
    private function dispatch(Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $responses,string $key,array $payload): JsonResponse
    {
        $tenant=workcore_tenant();$id=trim((string)$request->header('Idempotency-Key'));abort_if($id==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($key,$payload,$tenant->companyId(),(int)$tenant->userId(),$id,$request->header('X-WorkCore-Confirmation'),'api'));
        return $responses->action(new \App\Domains\WorkCore\System\Actions\ActionResult($result->actionKey,ContactResource::make($result->data),$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));
    }
}
