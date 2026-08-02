<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Automation\AutomationPolicy;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;

interface AutomationPolicyRepository
{
    public function forCompany(string $companyId, CurrencyCode $currency): AutomationPolicy;
}
