<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Sales;

enum SaleState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Completed = 'completed';
    case Voided = 'voided';
    case Refunded = 'refunded';
}
