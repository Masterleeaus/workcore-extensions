<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Contracts\KnowledgeBaseRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateKnowledgeArticle implements BusinessActionHandlerContract
{
    public function __construct(private KnowledgeBaseRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->createArticle($request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('knowledge_article',(string)$record['public_id']),[new PendingDomainEvent('workcore.knowledge.article.created',1,['article'=>$record])]);
    }
}
