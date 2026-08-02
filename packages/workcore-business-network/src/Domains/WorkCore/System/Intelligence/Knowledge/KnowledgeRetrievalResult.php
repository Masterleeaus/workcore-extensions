<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final readonly class KnowledgeRetrievalResult
{
    /** @param list<array<string,mixed>> $matches @param list<KnowledgeCitation> $citations */
    public function __construct(
        public array $matches,
        public int $retrievalTimeMs,
        public string $strategy,
        public array $citations = [],
        public int $totalCandidates = 0,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'matches' => $this->matches,
            'citations' => array_map(static fn (KnowledgeCitation $citation): array => $citation->toArray(), $this->citations),
            'retrieval_time_ms' => $this->retrievalTimeMs,
            'strategy' => $this->strategy,
            'total_candidates' => $this->totalCandidates,
        ];
    }
}
