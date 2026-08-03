<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts;

interface PrivilegedTenantAccessContract
{
    public function isActive(): bool;

    public function run(int|string $actorId, string $reason, callable $callback): mixed;
}
