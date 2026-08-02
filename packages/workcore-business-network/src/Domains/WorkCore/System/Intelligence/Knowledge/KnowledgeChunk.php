<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final readonly class KnowledgeChunk
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $content,
        public int $startOffset,
        public int $endOffset,
        public int $sequence,
        public array $metadata = [],
    ) {}
}
