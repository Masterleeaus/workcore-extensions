<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;

interface FinancialActionInvoker
{
    /** @param array<string,mixed> $payload */
    public function invoke(string $actionKey, array $payload, ActionContext $context): ActionResult;
}
