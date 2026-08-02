<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Budgets;

use InvalidArgumentException;

final readonly class IntelligenceBudget
{
    public function __construct(
        public int $maxCalls,
        public int $maxInputTokens,
        public int $maxOutputTokens,
        public int $maxCostMicros,
        public int $maxElapsedMs,
    ) {
        if (min($maxCalls, $maxInputTokens, $maxOutputTokens, $maxCostMicros, $maxElapsedMs) < 1) {
            throw new InvalidArgumentException('Intelligence budget limits must be positive.');
        }
    }
}
