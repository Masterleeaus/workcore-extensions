<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\ReconciliationSession;
use DateTimeImmutable;

interface ReconciliationRepository
{
    public function get(string $companyId, string $reconciliationId): ?ReconciliationSession;
    public function close(string $companyId, string $reconciliationId, ActionContext $context, DateTimeImmutable $closedAt): void;
}
