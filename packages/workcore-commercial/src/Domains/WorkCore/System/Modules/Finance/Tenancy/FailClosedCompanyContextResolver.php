<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Tenancy;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\CompanyContextResolver;
use App\Domains\WorkCore\System\Modules\Finance\Tenancy\Exceptions\MissingCompanyContextException;

final class FailClosedCompanyContextResolver implements CompanyContextResolver
{
    public function resolve(): CompanyContext
    {
        throw MissingCompanyContextException::forFinanceAction();
    }
}
