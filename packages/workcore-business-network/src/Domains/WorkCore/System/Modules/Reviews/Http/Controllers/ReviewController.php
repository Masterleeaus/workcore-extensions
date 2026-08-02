<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Reviews\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Reviews\Actions\SearchReviews;
use App\Domains\WorkCore\System\Modules\Reviews\Http\Requests\{StoreReviewRequest,RecordReviewRequest,PublishReviewRequest,ServiceRecoveryRequest,RebookingRequest};
use Illuminate\Http\{JsonResponse,Request};
final class ReviewController
{
 public function __construct(private BusinessActionDispatcher $dispatcher,private TenantContextContract $tenant){}
 public function index(Request $r,SearchReviews $search): JsonResponse{return response()->json(['data'=>$search($r->query(),$this->tenant->companyId(),(int)$r->query('per_page',25))]);}
 public function createRequest(StoreReviewRequest $r): JsonResponse{return $this->dispatch('workcore.review.request.create',$r->validated(),$r);}
 public function record(RecordReviewRequest $r): JsonResponse{return $this->dispatch('workcore.review.record',$r->validated(),$r);}
 public function publish(PublishReviewRequest $r,string $review): JsonResponse{return $this->dispatch('workcore.review.publish',array_merge($r->validated(),['review_public_id'=>$review]),$r);}
 public function recovery(ServiceRecoveryRequest $r,string $review): JsonResponse{return $this->dispatch('workcore.service_recovery.create',array_merge($r->validated(),['review_public_id'=>$review]),$r);}
 public function rebook(RebookingRequest $r): JsonResponse{return $this->dispatch('workcore.rebooking.request',$r->validated(),$r);}
 private function dispatch(string $key,array $payload,Request $r): JsonResponse{$result=$this->dispatcher->dispatch(new ActionRequest($key,$this->tenant->companyId(),$this->tenant->userId(),$payload,(string)$r->header('Idempotency-Key'),(string)$r->header('X-WorkCore-Confirmation')));return response()->json(['data'=>$result->data],201);}
}
