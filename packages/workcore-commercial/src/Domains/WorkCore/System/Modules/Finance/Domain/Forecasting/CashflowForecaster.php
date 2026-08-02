<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Forecasting;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class CashflowForecaster
{
    /** @param iterable<CashflowForecastItem> $items */
    public function monthly(Money $openingBalance, iterable $items, DateTimeImmutable $from, DateTimeImmutable $to): CashflowForecast
    {
        if ($to < $from) throw new InvalidArgumentException('Forecast end date must not precede start date.');
        $grouped=[];
        foreach ($items as $item) {
            if (! $item instanceof CashflowForecastItem) throw new InvalidArgumentException('Invalid cashflow forecast item.');
            if (! $openingBalance->currency->equals($item->amount->currency)) throw new InvalidArgumentException('Cashflow forecast cannot mix currencies.');
            if ($item->expectedDate < $from || $item->expectedDate > $to) continue;
            $grouped[$item->expectedDate->format('Y-m')][]=$item;
        }
        $periods=[]; $current=$from->modify('first day of this month'); $balance=$openingBalance;
        while ($current <= $to) {
            $key=$current->format('Y-m'); $in=Money::zero($openingBalance->currency); $out=Money::zero($openingBalance->currency);
            foreach ($grouped[$key] ?? [] as $item) {
                if ($item->direction===CashflowDirection::Inflow) $in=$in->add($item->amount); else $out=$out->add($item->amount);
            }
            $closing=$balance->add($in)->subtract($out);
            $periods[]=new CashflowForecastPeriod($key,$balance,$in,$out,$closing);
            $balance=$closing; $current=$current->modify('first day of next month');
        }
        return new CashflowForecast($periods);
    }
}
