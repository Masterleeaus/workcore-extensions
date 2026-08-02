<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Automation\AutomationPolicy;
use App\Domains\WorkCore\System\Modules\Finance\Automation\AutonomyLevel;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationPolicyRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use Illuminate\Support\Facades\DB;

final class DatabaseAutomationPolicyRepository implements AutomationPolicyRepository
{
    public function forCompany(string $companyId,CurrencyCode $currency): AutomationPolicy
    {
        $row=DB::table('tm_automation_policies')->where('company_id',$companyId)->where('policy_key','invoicing')->where('enabled',true)->first();
        if($row===null) return AutomationPolicy::defaultFor($companyId,AutonomyLevel::Approve,Money::zero($currency));
        return new AutomationPolicy($companyId,AutonomyLevel::from($row->autonomy_level),Money::ofMinor((int)($row->automatic_amount_limit_minor??0),$row->currency_code??$currency->value),(int)$row->automatic_match_threshold);
    }
}
