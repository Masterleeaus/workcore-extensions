<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime;

use DateTimeImmutable;

final readonly class ScheduledActionRecord
{
    /** @param array<string,mixed> $payload */
    public function __construct(public string $id,public string $companyId,public string $actionKey,public array $payload,public int $attemptCount,public ?string $workflowId,public string $correlationId,public DateTimeImmutable $runAt){}
}
