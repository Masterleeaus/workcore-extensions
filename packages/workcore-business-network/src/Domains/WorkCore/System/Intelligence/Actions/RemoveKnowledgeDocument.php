<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class RemoveKnowledgeDocument implements BusinessActionHandlerContract
{
    public function __construct(private KnowledgeIndexContract $index) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $documentId = trim((string) ($request->payload['document_id'] ?? ''));
        if ($documentId === '') {
            throw new InvalidArgumentException('Knowledge document ID is required.');
        }
        $this->index->remove($request->companyId, $documentId);

        return new ActionHandlerResult(
            data: ['document_id' => $documentId, 'removed' => true],
            aggregate: new TypedReference('ai_knowledge_document', $documentId),
            events: [new PendingDomainEvent('workcore.intelligence.knowledge_removed', 1, ['document_id' => $documentId])],
        );
    }
}
