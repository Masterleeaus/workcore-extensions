<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Tenancy\Exceptions;

use RuntimeException;

final class MissingCompanyContextException extends RuntimeException
{
    public static function forFinanceAction(): self
    {
        return new self('Titan Money rejected the request because no canonical active-company context was resolved.');
    }
}
