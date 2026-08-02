<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\RecurringServices\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract};

final class RecurringServicesToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [];
    }
}
