<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use RuntimeException;

final class MissingTenantContextException extends RuntimeException
{
}
