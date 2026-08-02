<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Forecasting;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CashflowForecastItem
{
    public function __construct(public string $sourceId, public DateTimeImmutable $expectedDate, public Money $amount, public CashflowDirection $direction, public int $confidenceBasisPoints=10000)
    {
        if ($amount->minorAmount < 0 || $confidenceBasisPoints<0 || $confidenceBasisPoints>10000) throw new InvalidArgumentException('Invalid cashflow item.');
    }
}
