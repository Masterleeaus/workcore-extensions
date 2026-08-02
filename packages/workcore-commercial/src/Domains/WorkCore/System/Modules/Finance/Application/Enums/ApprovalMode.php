<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Enums;

enum ApprovalMode: string
{
    case None = 'none';
    case UserConfirmation = 'user_confirmation';
    case ExplicitApproval = 'explicit_approval';
    case DualApproval = 'dual_approval';
}
