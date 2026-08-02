<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Payroll\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract};

final class PayrollToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [];
    }
}
