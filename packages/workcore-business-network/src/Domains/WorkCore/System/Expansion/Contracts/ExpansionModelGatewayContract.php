<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Contracts;

interface ExpansionModelGatewayContract
{
    /** @param array<string,mixed> $context @return array<string,mixed>|null */
    public function propose(array $context): ?array;
}
