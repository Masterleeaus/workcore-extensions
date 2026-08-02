<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;

interface PermissionResolverContract
{
    public function accessLevel(int|string $userId, int $companyId, string $permission): WorkCoreAccessLevel;

    public function allows(int|string $userId, int $companyId, string $permission): bool;
}
