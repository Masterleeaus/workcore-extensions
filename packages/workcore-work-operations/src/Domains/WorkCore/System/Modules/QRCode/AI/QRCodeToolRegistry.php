<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\QRCode\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract};

final class QRCodeToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [];
    }
}
