<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Forecasting;

enum CashflowDirection: string { case Inflow='inflow'; case Outflow='outflow'; }
