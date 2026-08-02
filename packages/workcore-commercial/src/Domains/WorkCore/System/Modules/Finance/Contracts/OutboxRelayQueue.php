<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxRelayRecord;
use DateTimeImmutable;

interface OutboxRelayQueue
{
    /** @return list<OutboxRelayRecord> */
    public function claimPending(DateTimeImmutable $now, int $limit, string $workerId, int $leaseSeconds): array;
    public function published(string $id, DateTimeImmutable $publishedAt, ?string $externalId = null): void;
    public function retry(string $id, string $errorCode, string $errorMessage, DateTimeImmutable $nextAttemptAt): void;
    public function deadLetter(string $id, string $errorCode, string $errorMessage, DateTimeImmutable $failedAt): void;
}
