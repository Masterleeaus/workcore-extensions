<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\AttendanceAccessContract;

final class AttendanceAccessAdapter extends AbstractDatabaseRecordAccess implements AttendanceAccessContract
{
    protected function table(): string { return 'tz_attendance_sessions'; }
    protected function type(): string { return 'attendance_session'; }
    protected function columns(): array
    {
        return ['public_id','worker_id','roster_shift_id','work_order_id','premises_id','status','clocked_in_at','clocked_out_at','timezone','source','scheduled_minutes','worked_minutes','break_minutes','notes','approved_at','created_at','updated_at'];
    }
}
