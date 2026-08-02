<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger;

enum LedgerSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
