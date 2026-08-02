<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Tax;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final class GstReportCalculator
{
    public function summarize(Money $gstCollected, Money $gstCredits): GstReportSummary
    {
        return new GstReportSummary($gstCollected, $gstCredits, $gstCollected->subtract($gstCredits));
    }
}
