<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Automation\AutomationPolicy;
use App\Domains\WorkCore\System\Modules\Finance\Automation\AutonomyLevel;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationPolicyRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final class InMemoryAutomationPolicyRepository implements AutomationPolicyRepository
{
    public function __construct(private readonly AutonomyLevel $level = AutonomyLevel::Managed, private readonly int $limitMinor = 1000000) {}
    public function forCompany(string $companyId, CurrencyCode $currency): AutomationPolicy
    {
        return AutomationPolicy::defaultFor($companyId, $this->level, Money::ofMinor($this->limitMinor, $currency));
    }
}
