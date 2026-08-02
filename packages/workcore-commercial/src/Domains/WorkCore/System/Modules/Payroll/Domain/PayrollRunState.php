<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Payroll\Domain;

enum PayrollRunState: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
}
