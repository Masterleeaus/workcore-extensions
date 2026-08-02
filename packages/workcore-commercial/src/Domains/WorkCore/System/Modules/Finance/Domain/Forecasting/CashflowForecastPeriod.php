<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Forecasting;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class CashflowForecastPeriod
{
    public function __construct(public string $periodKey, public Money $openingBalance, public Money $inflows, public Money $outflows, public Money $closingBalance) {}
    public function toArray(): array { return ['period_key'=>$this->periodKey,'opening_balance'=>$this->openingBalance->toArray(),'inflows'=>$this->inflows->toArray(),'outflows'=>$this->outflows->toArray(),'closing_balance'=>$this->closingBalance->toArray()]; }
}
