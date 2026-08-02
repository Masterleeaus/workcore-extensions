<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final readonly class KnowledgeContextWindow
{
    /** @param list<array<string,mixed>> $citations */
    public function __construct(
        public string $context,
        public array $citations,
        public int $usedTokens,
        public bool $truncated,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'context' => $this->context,
            'citations' => $this->citations,
            'used_tokens' => $this->usedTokens,
            'truncated' => $this->truncated,
        ];
    }
}
