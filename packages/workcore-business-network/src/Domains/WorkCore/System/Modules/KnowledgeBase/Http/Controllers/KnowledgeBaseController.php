<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\KnowledgeArticleResource;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Actions\SearchKnowledgeArticles;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests\{ChangeKnowledgeArticleStatusRequest,CreateArticleFromSupportTicketRequest,StoreKnowledgeArticleRequest,StoreKnowledgeCategoryRequest,StoreKnowledgeFeedbackRequest,UpdateKnowledgeArticleRequest};
use Illuminate\Http\{JsonResponse,Request};
final class KnowledgeBaseController
{
    public function index(Request $request,SearchKnowledgeArticles $search,ApiResponseFactory $api): JsonResponse{return $api->paginated($search->execute($request->only(['q','status','visibility','article_type','category_public_id','premises_public_id','support_ticket_public_id','published_only']),$request->integer('per_page',25)),KnowledgeArticleResource::make(...));}
    public function storeCategory(StoreKnowledgeCategoryRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.knowledge.category.create',$request->validated(),$request,$d,$api);}
    public function store(StoreKnowledgeArticleRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.knowledge.article.create',$request->validated(),$request,$d,$api);}
    public function update(string $article,UpdateKnowledgeArticleRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.knowledge.article.update',['knowledge_article_public_id'=>$article]+$request->validated(),$request,$d,$api);}
    public function status(string $article,ChangeKnowledgeArticleStatusRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.knowledge.article.change_status',['knowledge_article_public_id'=>$article]+$request->validated(),$request,$d,$api);}
    public function fromTicket(string $ticket,CreateArticleFromSupportTicketRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.knowledge.article.create_from_support_ticket',['support_ticket_public_id'=>$ticket]+$request->validated(),$request,$d,$api);}
    public function feedback(string $article,StoreKnowledgeFeedbackRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.knowledge.feedback.record',['knowledge_article_public_id'=>$article]+$request->validated(),$request,$d,$api);}
    private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api): JsonResponse{$tenant=workcore_tenant();$idempotency=trim((string)$request->header('Idempotency-Key'));abort_if($idempotency==='',422,'Idempotency-Key header is required.');$result=$dispatcher->dispatch(new ActionRequest($key,$payload,(int)$tenant->companyId(),(int)$tenant->userId(),$idempotency,$request->header('X-WorkCore-Confirmation'),'api'));return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));}
}
