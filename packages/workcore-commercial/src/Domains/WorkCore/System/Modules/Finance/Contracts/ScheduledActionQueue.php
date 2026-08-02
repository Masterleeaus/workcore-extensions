<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime\ScheduledActionRecord;
use DateTimeImmutable;

interface ScheduledActionQueue
{
    /** @return list<ScheduledActionRecord> */
    public function claimDue(DateTimeImmutable $now, int $limit, string $workerId, int $leaseSeconds): array;
    /** @param array<string,mixed> $result */
    public function complete(string $id, array $result, DateTimeImmutable $finishedAt): void;
    public function retry(string $id, string $errorCode, string $errorMessage, DateTimeImmutable $nextRunAt): void;
    public function fail(string $id, string $errorCode, string $errorMessage, DateTimeImmutable $finishedAt): void;
}
