<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;

interface FinancialExceptionRepository
{
    /** @param array<string,mixed> $evidence */
    public function create(string $type, string $severity, ?string $entityType, ?string $entityId, array $evidence, ActionContext $context): string;
}
