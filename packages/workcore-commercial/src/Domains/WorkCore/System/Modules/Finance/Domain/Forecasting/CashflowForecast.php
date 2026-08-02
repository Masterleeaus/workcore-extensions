<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Forecasting;

final readonly class CashflowForecast
{
    /** @param list<CashflowForecastPeriod> $periods */
    public function __construct(public array $periods) {}
    public function toArray(): array { return ['periods'=>array_map(static fn(CashflowForecastPeriod $p)=>$p->toArray(),$this->periods)]; }
}
