<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

final readonly class PendingDomainEvent
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $name,
        public int $version = 1,
        public array $payload = [],
    ) {}
}
