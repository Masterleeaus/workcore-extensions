<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use DateTimeImmutable;

interface AutomationScheduler
{
    public function schedule(string $actionKey,array $payload,DateTimeImmutable $runAt,ActionContext $context): string;
    public function cancel(string $scheduledActionId,ActionContext $context): void;
    public function cancelForEntity(string $companyId,string $entityType,string $entityId,ActionContext $context): int;
}
