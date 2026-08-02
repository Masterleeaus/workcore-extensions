<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

enum OfflineAttendanceBatchState: string
{
    case Open = 'open';
    case Sealed = 'sealed';
    case Synced = 'synced';
    case Rejected = 'rejected';
}
