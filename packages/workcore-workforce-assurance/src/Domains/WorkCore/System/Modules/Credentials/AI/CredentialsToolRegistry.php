<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Credentials\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract};

final class CredentialsToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [];
    }
}
