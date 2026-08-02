<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge\Retention;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class KnowledgeRetentionPolicy
{
    public function __construct(
        public int $defaultRetentionDays = 365,
        public int $deletedGraceDays = 7,
    ) {
        if ($defaultRetentionDays < 1 || $defaultRetentionDays > 3650 || $deletedGraceDays < 0 || $deletedGraceDays > 365) {
            throw new InvalidArgumentException('Knowledge retention configuration is invalid.');
        }
    }

    public function isExpired(?string $expiresAt, ?string $createdAt, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();
        if ($expiresAt !== null) {
            return new DateTimeImmutable($expiresAt) <= $now;
        }

        if ($createdAt === null) {
            return false;
        }

        return new DateTimeImmutable($createdAt) <= $now->modify("-{$this->defaultRetentionDays} days");
    }

    public function purgeAfter(string $deletedAt): DateTimeImmutable
    {
        return (new DateTimeImmutable($deletedAt))->modify("+{$this->deletedGraceDays} days");
    }
}
