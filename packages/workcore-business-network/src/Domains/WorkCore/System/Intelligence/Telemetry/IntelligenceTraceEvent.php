<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Telemetry;

final readonly class IntelligenceTraceEvent
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public string $name,
        public string $timestamp,
        public array $attributes,
        public ?string $correlationId = null,
        public ?string $causationId = null,
    ) {}
}
