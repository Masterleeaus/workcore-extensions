<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\DispatchAssignmentAccessContract;

final class DispatchAssignmentAccessAdapter extends AbstractDatabaseRecordAccess implements DispatchAssignmentAccessContract
{
    protected function table(): string { return 'tz_dispatch_assignments'; }
    protected function type(): string { return 'dispatch_assignment'; }
    protected function columns(): array
    {
        return ['public_id','dispatch_number','work_order_id','appointment_id','premises_id','assigned_user_id','assignment_type','priority','status','scheduled_start','scheduled_end','timezone','route_group','sequence','instructions','access_notes','travel_distance_meters','travel_duration_seconds','source','created_at','updated_at'];
    }
}
