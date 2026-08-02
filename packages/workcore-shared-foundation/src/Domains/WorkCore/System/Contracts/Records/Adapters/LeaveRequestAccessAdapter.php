<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\LeaveRequestAccessContract;

final class LeaveRequestAccessAdapter extends AbstractDatabaseRecordAccess implements LeaveRequestAccessContract
{
    protected function table(): string { return 'tz_leave_requests'; }
    protected function type(): string { return 'leave_request'; }
    protected function columns(): array
    {
        return ['public_id','worker_id','leave_type_id','replacement_worker_id','starts_at','ends_at','timezone','requested_minutes','status','reason','notes','document_path','affects_roster','submitted_at','decided_at','decision_reason','created_at','updated_at'];
    }
}
