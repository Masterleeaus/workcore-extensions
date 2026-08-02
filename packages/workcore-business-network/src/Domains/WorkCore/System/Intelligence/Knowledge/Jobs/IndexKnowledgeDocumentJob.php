<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge\Jobs;

use App\Domains\WorkCore\System\Intelligence\Execution\TenantScopedIntelligenceJob;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeDocument;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIngestionService;
use App\Domains\WorkCore\System\Queue\WorkCoreContextPayloadFactory;

final class IndexKnowledgeDocumentJob extends TenantScopedIntelligenceJob
{
    /** @param array<string,mixed> $document */
    public function __construct(
        public readonly array $document,
        ?WorkCoreContextPayloadFactory $contextFactory = null,
    ) {
        parent::__construct('knowledge.index', $contextFactory);
    }

    public function handle(KnowledgeIngestionService $ingestion): void
    {
        $ingestion->ingest(new KnowledgeDocument(
            companyId: (int) $this->document['company_id'],
            documentId: (string) $this->document['document_id'],
            content: (string) $this->document['content'],
            visibility: (string) ($this->document['visibility'] ?? 'company'),
            metadata: (array) ($this->document['metadata'] ?? []),
            title: (string) ($this->document['title'] ?? ''),
            sourceType: (string) ($this->document['source_type'] ?? 'manual'),
            sourcePublicId: isset($this->document['source_public_id']) ? (string) $this->document['source_public_id'] : null,
            requiredPermission: isset($this->document['required_permission']) ? (string) $this->document['required_permission'] : null,
            ownerUserId: isset($this->document['owner_user_id']) ? (int) $this->document['owner_user_id'] : null,
            sourceUpdatedAt: isset($this->document['source_updated_at']) ? (string) $this->document['source_updated_at'] : null,
            expiresAt: isset($this->document['expires_at']) ? (string) $this->document['expires_at'] : null,
            createdByUserId: isset($this->document['created_by_user_id']) ? (int) $this->document['created_by_user_id'] : null,
        ));
    }
}
