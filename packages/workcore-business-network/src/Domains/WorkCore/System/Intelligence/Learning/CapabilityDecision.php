<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Learning;

final readonly class CapabilityDecision
{
    /** @param list<array<string,mixed>> $alternatives */
    public function __construct(
        public string $decision,
        public ?string $matchedKey,
        public float $score,
        public array $alternatives = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision,
            'matched_key' => $this->matchedKey,
            'score' => $this->score,
            'alternatives' => $this->alternatives,
        ];
    }
}
