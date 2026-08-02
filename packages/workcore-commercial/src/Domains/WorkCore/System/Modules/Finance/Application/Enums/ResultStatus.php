<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Enums;

enum ResultStatus: string
{
    case Success = 'success';
    case PendingApproval = 'pending_approval';
    case ValidationFailed = 'validation_failed';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
