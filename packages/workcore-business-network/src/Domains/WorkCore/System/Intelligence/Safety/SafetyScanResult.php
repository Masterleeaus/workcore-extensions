<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Safety;

final readonly class SafetyScanResult
{
    /** @param list<SafetyFinding> $findings */
    public function __construct(
        public string $sanitized,
        public bool $blocked,
        public array $findings,
        public string $trustBoundary,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'blocked' => $this->blocked,
            'sanitized' => $this->sanitized,
            'trust_boundary' => $this->trustBoundary,
            'findings' => array_map(static fn (SafetyFinding $finding): array => $finding->toArray(), $this->findings),
        ];
    }
}
