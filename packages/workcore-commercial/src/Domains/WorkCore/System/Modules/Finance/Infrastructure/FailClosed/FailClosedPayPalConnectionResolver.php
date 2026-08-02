<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\PayPalConnectionResolver;
use RuntimeException;

final class FailClosedPayPalConnectionResolver implements PayPalConnectionResolver
{
    public function resolveCompanyId(string $connectionKey): string
    {
        throw new RuntimeException('PayPal connection-to-company resolver is not bound.');
    }
}
