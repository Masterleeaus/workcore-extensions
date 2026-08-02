<?php

declare(strict_types=1);

namespace App\Support\WorkCore;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use Illuminate\Support\Facades\DB;

final class WorkCorePermissionResolver implements PermissionResolverContract
{
    public function accessLevel(int|string $userId, int $companyId, string $permission): WorkCoreAccessLevel
    {
        $membership = DB::table('tz_company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            return WorkCoreAccessLevel::None;
        }

        if ((bool) $membership->is_owner || in_array((string) $membership->role_key, ['owner', 'admin'], true)) {
            return WorkCoreAccessLevel::Manage;
        }

        $memberLevel = DB::table('tz_company_member_permissions')
            ->where('company_id', $companyId)
            ->where('membership_id', $membership->id)
            ->where('permission', $permission)
            ->value('access_level');

        if (is_string($memberLevel)) {
            return WorkCoreAccessLevel::tryFrom($memberLevel) ?? WorkCoreAccessLevel::None;
        }

        if ($membership->role_id !== null) {
            $roleLevel = DB::table('tz_company_role_permissions')
                ->where('company_id', $companyId)
                ->where('role_id', $membership->role_id)
                ->where('permission', $permission)
                ->value('access_level');

            if (is_string($roleLevel)) {
                return WorkCoreAccessLevel::tryFrom($roleLevel) ?? WorkCoreAccessLevel::None;
            }
        }

        return WorkCoreAccessLevel::None;
    }

    public function allows(int|string $userId, int $companyId, string $permission): bool
    {
        return $this->accessLevel($userId, $companyId, $permission) !== WorkCoreAccessLevel::None;
    }
}
