<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Context;

final readonly class IntelligenceContext
{
    /** @param list<string> $permissions @param list<string> $capabilities @param array<string,mixed> $metadata */
    public function __construct(
        public int $companyId,
        public int $actorId,
        public string $correlationId,
        public ?string $causationId,
        public array $permissions,
        public array $capabilities,
        public array $metadata = [],
    ) {}
}
