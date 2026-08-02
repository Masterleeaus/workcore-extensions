<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Tenancy\CompanyContext;

interface CompanyContextResolver
{
    public function resolve(): CompanyContext;
}
