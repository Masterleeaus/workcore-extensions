<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Authorization;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PermissionAuthorizer;
use RuntimeException;

final class FailClosedPermissionAuthorizer implements PermissionAuthorizer
{
    public function assertAllowed(ActionContext $context, string $permission): void
    {
        throw new RuntimeException(
            "Titan Money denied {$permission} because no Platform Core permission adapter is registered.",
        );
    }
}
