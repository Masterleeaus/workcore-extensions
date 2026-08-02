<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Contracts\KnowledgeBaseRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateKnowledgeCategory implements BusinessActionHandlerContract
{
    public function __construct(private KnowledgeBaseRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->createCategory($request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('knowledge_category',(string)$record['public_id']),[new PendingDomainEvent('workcore.knowledge.category.created',1,['category'=>$record])]);
    }
}
