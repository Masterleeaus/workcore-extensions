<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Settings;

enum GstAccountingBasis: string
{
    case Cash = 'cash';
    case Accrual = 'accrual';
}
