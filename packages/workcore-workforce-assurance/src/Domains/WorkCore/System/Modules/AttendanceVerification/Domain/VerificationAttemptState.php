<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

enum VerificationAttemptState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Consumed = 'consumed';
}
