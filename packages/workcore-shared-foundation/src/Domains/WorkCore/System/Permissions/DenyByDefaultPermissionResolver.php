<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Permissions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;

final class DenyByDefaultPermissionResolver implements PermissionResolverContract
{
    public function accessLevel(int|string $userId, int $companyId, string $permission): WorkCoreAccessLevel
    {
        return WorkCoreAccessLevel::None;
    }

    public function allows(int|string $userId, int $companyId, string $permission): bool
    {
        return false;
    }
}
