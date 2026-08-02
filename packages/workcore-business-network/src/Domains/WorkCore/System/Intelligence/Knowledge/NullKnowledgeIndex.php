<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final class NullKnowledgeIndex implements KnowledgeIndexContract, KnowledgeIndexStatusContract
{
    public function index(KnowledgeDocument $document): array
    {
        return ['indexed' => false, 'reason' => 'knowledge_index_not_configured', 'document_id' => $document->documentId];
    }

    public function remove(int $companyId, string $documentId): void
    {
    }

    public function search(KnowledgeQuery $query): KnowledgeRetrievalResult
    {
        return new KnowledgeRetrievalResult([], 0, 'null');
    }

    public function status(int $companyId, string $documentId): ?array
    {
        return null;
    }

    public function purgeExpired(int $companyId, ?string $before = null): int
    {
        return 0;
    }
}
