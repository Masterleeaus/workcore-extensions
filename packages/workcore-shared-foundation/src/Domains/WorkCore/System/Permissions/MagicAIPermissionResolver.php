<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Permissions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Identity\MagicAIUserCompanyAdapter;
use App\Domains\WorkCore\System\Models\CompanyMemberPermission;
use App\Domains\WorkCore\System\Models\CompanyRolePermission;

final class MagicAIPermissionResolver implements PermissionResolverContract
{
    public function __construct(private MagicAIUserCompanyAdapter $adapter) {}

    public function accessLevel(int|string $userId, int $companyId, string $permission): WorkCoreAccessLevel
    {
        $membership = $this->adapter->membership((int) $userId, $companyId);
        if ($membership === null) {
            return WorkCoreAccessLevel::None;
        }

        if ($membership->is_owner || $membership->role_key === 'owner') {
            return WorkCoreAccessLevel::All;
        }

        $override = CompanyMemberPermission::query()
            ->where('company_id', $companyId)
            ->where('membership_id', $membership->getKey())
            ->where('permission', $permission)
            ->value('access_level');

        if (is_string($override)) {
            return WorkCoreAccessLevel::tryFrom($override) ?? WorkCoreAccessLevel::None;
        }

        if ($membership->role_id !== null) {
            $roleLevel = CompanyRolePermission::query()
                ->where('company_id', $companyId)
                ->where('role_id', $membership->role_id)
                ->where('permission', $permission)
                ->value('access_level');

            if (is_string($roleLevel)) {
                return WorkCoreAccessLevel::tryFrom($roleLevel) ?? WorkCoreAccessLevel::None;
            }
        }

        if ((bool) config('workcore.permissions.allow_platform_fallback', false)) {
            $userModel = (string) config('workcore.identity.user_model');
            $user = $userModel::query()->find((int) $userId);
            if ($user !== null && method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission)) {
                return WorkCoreAccessLevel::All;
            }
        }

        return WorkCoreAccessLevel::None;
    }

    public function allows(int|string $userId, int $companyId, string $permission): bool
    {
        return $this->accessLevel($userId, $companyId, $permission)->grantsAnyAccess();
    }
}
