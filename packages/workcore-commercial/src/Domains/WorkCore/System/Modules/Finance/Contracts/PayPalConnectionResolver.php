<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface PayPalConnectionResolver
{
    public function resolveCompanyId(string $connectionKey): string;
}
