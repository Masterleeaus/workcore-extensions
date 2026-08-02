<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\RecurringServices\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\ServiceAgreementResource;
use App\Domains\WorkCore\System\Modules\RecurringServices\Actions\SearchServiceAgreements;
use App\Domains\WorkCore\System\Modules\RecurringServices\Http\Requests\{GenerateRecurringServicesRequest,RenewServiceAgreementRequest,RescheduleRecurringOccurrenceRequest,ServiceAgreementStatusRequest,SkipRecurringOccurrenceRequest,StoreServiceAgreementRequest};
use Illuminate\Http\{JsonResponse,Request};
final class RecurringServiceController
{
    public function index(Request $r,SearchServiceAgreements $search,ApiResponseFactory $api): JsonResponse{$t=workcore_tenant();return $api->paginated($search($r->query(),(int)$t->companyId(),(int)$r->query('per_page',25)),ServiceAgreementResource::make(...));}
    public function store(StoreServiceAgreementRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.agreement.create',$r->validated(),$r,$d,$api);}
    public function status(ServiceAgreementStatusRequest $r,string $agreement,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.agreement.change_status',['agreement_public_id'=>$agreement]+$r->validated(),$r,$d,$api);}
    public function generate(GenerateRecurringServicesRequest $r,string $agreement,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.generate',['agreement_public_id'=>$agreement]+$r->validated(),$r,$d,$api);}
    public function generateAll(GenerateRecurringServicesRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.generate',$r->validated(),$r,$d,$api);}
    public function skip(SkipRecurringOccurrenceRequest $r,string $occurrence,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.occurrence.skip',['occurrence_public_id'=>$occurrence]+$r->validated(),$r,$d,$api);}
    public function reschedule(RescheduleRecurringOccurrenceRequest $r,string $occurrence,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.occurrence.reschedule',['occurrence_public_id'=>$occurrence]+$r->validated(),$r,$d,$api);}
    public function renew(RenewServiceAgreementRequest $r,string $agreement,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.recurring.agreement.renew',['agreement_public_id'=>$agreement]+$r->validated(),$r,$d,$api);}
    private function dispatch(string $key,array $payload,Request $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{$t=workcore_tenant();$id=trim((string)$r->header('Idempotency-Key'));abort_if($id==='',422,'Idempotency-Key header is required.');$result=$d->dispatch(new ActionRequest($key,$payload,(int)$t->companyId(),(int)$t->userId(),$id,$r->header('X-WorkCore-Confirmation'),'api'));return $api->action($result);}
}
