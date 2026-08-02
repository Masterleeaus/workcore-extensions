<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

interface DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array;
}
