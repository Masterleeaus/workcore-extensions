<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Authorization;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CompanyMembershipAuthorizer;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PermissionAuthorizer;
use InvalidArgumentException;

final readonly class FinanceAccessGate
{
    public function __construct(
        private CompanyMembershipAuthorizer $memberships,
        private PermissionAuthorizer $permissions,
    ) {
    }

    public function assertAllowed(ActionContext $context, TitanMoneyPermission|string $permission): void
    {
        $permissionValue = $permission instanceof TitanMoneyPermission ? $permission->value : trim($permission);
        if (! str_starts_with($permissionValue, 'money.')) {
            throw new InvalidArgumentException('Titan Money permissions must use the money.* namespace.');
        }

        $this->memberships->assertMembership($context);
        $this->permissions->assertAllowed($context, $permissionValue);
    }
}
