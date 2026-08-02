<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Tax;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use InvalidArgumentException;

final class GstCalculator
{
    public function fromInclusive(Money $total, int $rateBasisPoints = 1000): GstBreakdown
    {
        $this->assertInputs($total, $rateBasisPoints);
        $taxMinor = $this->roundHalfUp($total->minorAmount * $rateBasisPoints, 10000 + $rateBasisPoints);
        $tax = Money::ofMinor($taxMinor, $total->currency);
        return new GstBreakdown($total->subtract($tax), $tax, $total, $rateBasisPoints);
    }

    public function fromExclusive(Money $subtotal, int $rateBasisPoints = 1000): GstBreakdown
    {
        $this->assertInputs($subtotal, $rateBasisPoints);
        $taxMinor = $this->roundHalfUp($subtotal->minorAmount * $rateBasisPoints, 10000);
        $tax = Money::ofMinor($taxMinor, $subtotal->currency);
        return new GstBreakdown($subtotal, $tax, $subtotal->add($tax), $rateBasisPoints);
    }

    private function assertInputs(Money $money, int $rateBasisPoints): void
    {
        if ($money->isNegative()) throw new InvalidArgumentException('GST calculations require a non-negative amount.');
        if ($rateBasisPoints < 0 || $rateBasisPoints > 10000) throw new InvalidArgumentException('GST rate basis points must be between 0 and 10000.');
    }

    private function roundHalfUp(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
