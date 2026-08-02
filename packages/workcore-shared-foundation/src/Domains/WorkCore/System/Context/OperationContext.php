<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use RuntimeException;

final class OperationContext implements OperationContextContract
{
    private ?OperationContextSnapshot $current = null;

    public function hasContext(): bool { return $this->current !== null; }
    public function companyId(): int { return $this->snapshot()->companyId; }
    public function actorId(): ?int { return $this->snapshot()->actorId; }
    public function correlationId(): string { return $this->snapshot()->correlationId; }
    public function causationId(): ?string { return $this->snapshot()->causationId; }
    public function locale(): string { return $this->snapshot()->locale; }
    public function timezone(): string { return $this->snapshot()->timezone; }

    public function set(int $companyId, ?int $actorId = null, ?string $correlationId = null, ?string $causationId = null, ?string $locale = null, ?string $timezone = null): void
    {
        $this->current = new OperationContextSnapshot(
            companyId: $companyId,
            actorId: $actorId,
            correlationId: $correlationId ?: bin2hex(random_bytes(16)),
            causationId: $causationId,
            locale: $locale ?: 'en_AU',
            timezone: $timezone ?: 'Australia/Melbourne',
        );
    }

    public function restore(OperationContextSnapshot $snapshot): void { $this->current = $snapshot; }

    public function snapshot(): OperationContextSnapshot
    {
        if ($this->current === null) {
            throw new RuntimeException('WorkCore operation context has not been resolved.');
        }
        return $this->current;
    }

    public function clear(): void { $this->current = null; }
}
