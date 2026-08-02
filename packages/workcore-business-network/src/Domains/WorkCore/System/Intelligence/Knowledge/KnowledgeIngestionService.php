<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use App\Domains\WorkCore\System\Intelligence\Safety\ContentSafetyScanner;
use App\Domains\WorkCore\System\Intelligence\Safety\SecretScanner;
use InvalidArgumentException;

final class KnowledgeIngestionService
{
    public function __construct(
        private KnowledgeIndexContract $knowledgeIndex,
        private ContentSafetyScanner $safety,
        private SecretScanner $secrets,
    ) {}

    /** @return array<string,mixed> */
    public function ingest(KnowledgeDocument $document): array
    {
        $secretFindings = $this->secrets->detect($document->content);
        $scan = $this->safety->scan($document->content, 'knowledge_index');
        if ($scan->blocked || $secretFindings !== []) {
            throw new InvalidArgumentException('Knowledge content contains blocked secret material.');
        }

        $result = $this->knowledgeIndex->index($document);
        $result['content_hash'] = $document->contentHash();
        $result['safety_findings'] = count($scan->findings);
        $result['source_type'] = $document->sourceType;
        $result['source_public_id'] = $document->sourcePublicId;

        return $result;
    }
}
