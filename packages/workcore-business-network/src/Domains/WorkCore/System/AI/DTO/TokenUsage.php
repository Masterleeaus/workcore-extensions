<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $totalTokens = 0,
        public int $cachedInputTokens = 0,
        public ?float $estimatedCost = null,
        public ?string $currency = null,
    ) {}

    /** @return array<string,int|float|string|null> */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'cached_input_tokens' => $this->cachedInputTokens,
            'estimated_cost' => $this->estimatedCost,
            'currency' => $this->currency,
        ];
    }
}
