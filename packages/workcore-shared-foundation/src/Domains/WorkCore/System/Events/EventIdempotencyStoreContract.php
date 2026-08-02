<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Events;

interface EventIdempotencyStoreContract
{
    public function hasProcessed(string $consumer, string $idempotencyKey): bool;

    public function markProcessed(string $consumer, string $idempotencyKey, ?\DateTimeImmutable $expiresAt = null): void;
}
