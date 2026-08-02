<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Gateways;

use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionModelGatewayContract;

final class NullExpansionModelGateway implements ExpansionModelGatewayContract
{
    public function propose(array $context): ?array
    {
        return null;
    }
}
