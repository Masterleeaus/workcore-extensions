<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use DateTimeImmutable;
use InvalidArgumentException;

final class ReceivablesAgeingCalculator
{
    /** @param iterable<ReceivableBalance> $balances */
    public function calculate(iterable $balances, DateTimeImmutable $asOf): AgeingReport
    {
        return $this->calculateInternal($balances, $asOf, ReceivableBalance::class);
    }

    private function calculateInternal(iterable $balances, DateTimeImmutable $asOf, string $expected): AgeingReport
    {
        $amounts=array_fill_keys(array_map(static fn(AgeingBucket $b)=>$b->value, AgeingBucket::cases()),0);
        $currency=null; $count=0;
        foreach ($balances as $balance) {
            if (! $balance instanceof $expected) throw new InvalidArgumentException('Invalid receivable balance.');
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
