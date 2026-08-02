<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Profitability;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final class JobProfitabilityCalculator
{
    public function calculate(string $jobId, Money $revenue, Money $labourCost, Money $materialCost, Money $otherCost): JobProfitabilityResult
    {
        $totalCost=$labourCost->add($materialCost)->add($otherCost);
        $profit=$revenue->subtract($totalCost);
        $margin = null;
        if ($revenue->minorAmount !== 0) {
            $numerator = $profit->minorAmount * 10000;
            $denominator = abs($revenue->minorAmount);
            $rounded = intdiv(abs($numerator) + intdiv($denominator, 2), $denominator);
            $margin = $numerator < 0 ? -$rounded : $rounded;
        }
        return new JobProfitabilityResult($jobId,$revenue,$labourCost,$materialCost,$otherCost,$totalCost,$profit,$margin);
    }
}
