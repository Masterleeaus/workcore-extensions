<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Data;

use InvalidArgumentException;

final readonly class ExpansionAssessment
{
    /** @param array<int,array<string,mixed>> $alternatives */
    public function __construct(
        public string $status,
        public string $normalisedRequirement,
        public ?string $matchedKey,
        public float $score,
        public array $alternatives = [],
        public ?string $matchType = null,
    ) {
        if (! in_array($status, ['existing', 'extend_existing', 'missing'], true)) {
            throw new InvalidArgumentException("Invalid expansion assessment status [{$status}].");
        }
    }

    public function requiresExpansion(): bool
    {
        return $this->status !== 'existing';
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'normalised_requirement' => $this->normalisedRequirement,
            'matched_key' => $this->matchedKey,
            'score' => round($this->score, 4),
            'match_type' => $this->matchType,
            'alternatives' => $this->alternatives,
        ];
    }
}
