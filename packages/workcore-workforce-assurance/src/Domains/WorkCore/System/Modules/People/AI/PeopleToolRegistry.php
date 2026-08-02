<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract};

final class PeopleToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [];
    }
}
