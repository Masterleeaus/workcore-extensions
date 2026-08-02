<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;

interface PermissionAuthorizer
{
    public function assertAllowed(ActionContext $context, string $permission): void;
}
