<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Automation\AutomationPolicy;
use App\Domains\WorkCore\System\Modules\Finance\Automation\AutonomyLevel;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationPolicyRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final class FailClosedAutomationPolicyRepository implements AutomationPolicyRepository
{
    public function forCompany(string $companyId,CurrencyCode $currency): AutomationPolicy { return AutomationPolicy::defaultFor($companyId,AutonomyLevel::Approve,Money::zero($currency)); }
}
