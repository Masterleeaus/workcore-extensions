<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final readonly class KnowledgeCitation
{
    public function __construct(
        public string $documentId,
        public string $chunkId,
        public string $title,
        public string $sourceType,
        public ?string $sourcePublicId,
        public ?string $sourceUpdatedAt,
        public ?string $indexedAt,
        public bool $stale,
        public string $excerpt,
        public float $score,
    ) {
        if (trim($documentId) === '' || trim($chunkId) === '' || $score < 0.0 || $score > 1.0) {
            throw new InvalidArgumentException('Knowledge citation is invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'document_id' => $this->documentId,
            'chunk_id' => $this->chunkId,
            'title' => $this->title,
            'source_type' => $this->sourceType,
            'source_public_id' => $this->sourcePublicId,
            'source_updated_at' => $this->sourceUpdatedAt,
            'indexed_at' => $this->indexedAt,
            'stale' => $this->stale,
            'excerpt' => strlen($this->excerpt) <= 320 ? $this->excerpt : substr($this->excerpt, 0, 317) . '...',
            'score' => round($this->score, 6),
        ];
    }
}
