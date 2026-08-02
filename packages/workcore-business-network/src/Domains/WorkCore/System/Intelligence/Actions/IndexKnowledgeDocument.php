<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeDocument;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIngestionService;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeSourceDescriptor;
use App\Domains\WorkCore\System\References\TypedReference;

final class IndexKnowledgeDocument implements BusinessActionHandlerContract
{
    public function __construct(private KnowledgeIngestionService $ingestion) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $sourceType = trim((string) ($request->payload['source_type'] ?? 'manual'));
        $sourcePublicId = trim((string) ($request->payload['source_public_id'] ?? ''));
        $title = trim((string) ($request->payload['title'] ?? ''));
        $documentId = trim((string) ($request->payload['document_id'] ?? ''));
        if ($documentId === '') {
            $documentId = (new KnowledgeSourceDescriptor(
                $sourceType,
                $sourcePublicId !== '' ? $sourcePublicId : hash('sha256', $title . '|' . (string) ($request->payload['content'] ?? '')),
                $title,
            ))->stableDocumentId($request->companyId);
        }

        $document = new KnowledgeDocument(
            companyId: $request->companyId,
            documentId: $documentId,
            content: (string) ($request->payload['content'] ?? ''),
            visibility: trim((string) ($request->payload['visibility'] ?? 'company')),
            metadata: (array) ($request->payload['metadata'] ?? []),
            title: $title,
            sourceType: $sourceType,
            sourcePublicId: $sourcePublicId !== '' ? $sourcePublicId : null,
            requiredPermission: $this->nullableString($request->payload['required_permission'] ?? null),
            ownerUserId: isset($request->payload['owner_user_id']) ? (int) $request->payload['owner_user_id'] : null,
            sourceUpdatedAt: $this->nullableString($request->payload['source_updated_at'] ?? null),
            expiresAt: $this->nullableString($request->payload['expires_at'] ?? null),
            createdByUserId: $request->actorId,
        );
        $result = $this->ingestion->ingest($document);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('ai_knowledge_document', $documentId),
            events: [new PendingDomainEvent('workcore.intelligence.knowledge_indexed', 1, [
                'document_id' => $documentId,
                'source_type' => $sourceType,
                'source_public_id' => $document->sourcePublicId,
                'content_hash' => $document->contentHash(),
                'chunk_count' => (int) ($result['chunk_count'] ?? 0),
                'unchanged' => (bool) ($result['unchanged'] ?? false),
            ])],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
