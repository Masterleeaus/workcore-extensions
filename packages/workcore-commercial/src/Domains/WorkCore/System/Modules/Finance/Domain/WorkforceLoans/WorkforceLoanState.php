<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\WorkforceLoans;

enum WorkforceLoanState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Declined = 'declined';
    case Active = 'active';
    case Repaid = 'repaid';
    case WrittenOff = 'written_off';
}
