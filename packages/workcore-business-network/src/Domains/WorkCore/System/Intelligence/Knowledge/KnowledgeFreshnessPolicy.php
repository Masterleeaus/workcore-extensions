<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class KnowledgeFreshnessPolicy
{
    public function __construct(public int $staleAfterDays = 90)
    {
        if ($staleAfterDays < 1 || $staleAfterDays > 3650) {
            throw new InvalidArgumentException('Knowledge stale-after days must be between 1 and 3650.');
        }
    }

    public function isStale(?string $sourceUpdatedAt, ?string $indexedAt, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();
        $reference = $sourceUpdatedAt ?? $indexedAt;
        if ($reference === null || strtotime($reference) === false) {
            return true;
        }

        return new DateTimeImmutable($reference) < $now->modify("-{$this->staleAfterDays} days");
    }
}
