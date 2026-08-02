<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use DateTimeImmutable;
use InvalidArgumentException;

final class PayablesAgeingCalculator
{
    /** @param iterable<PayableBalance> $balances */
    public function calculate(iterable $balances, DateTimeImmutable $asOf): AgeingReport
    {
        $amounts=array_fill_keys(array_map(static fn(AgeingBucket $b)=>$b->value, AgeingBucket::cases()),0);
        $currency=null; $count=0;
        foreach ($balances as $balance) {
            if (! $balance instanceof PayableBalance) throw new InvalidArgumentException('Invalid payable balance.');
            $currency ??= $balance->amountDue->currency;
            if (! $currency->equals($balance->amountDue->currency)) throw new InvalidArgumentException('Ageing report cannot mix currencies.');
            if ($balance->amountDue->minorAmount <= 0) continue;
            $days=$balance->dueDate > $asOf ? 0 : (int)$balance->dueDate->diff($asOf)->format('%a');
            $amounts[AgeingBucket::fromDaysOverdue($days)->value]+=$balance->amountDue->minorAmount;
            $count++;
        }
        return new AgeingReport($currency ?? new CurrencyCode('AUD'), $amounts, $count);
    }
}
