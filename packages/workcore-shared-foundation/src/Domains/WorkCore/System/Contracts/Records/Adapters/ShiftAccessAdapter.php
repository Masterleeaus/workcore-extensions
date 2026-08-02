<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\ShiftAccessContract;

final class ShiftAccessAdapter extends AbstractDatabaseRecordAccess implements ShiftAccessContract
{
    protected function table(): string { return 'tz_roster_shifts'; }
    protected function type(): string { return 'roster_shift'; }
    protected function columns(): array { return ['public_id','roster_id','premises_id','work_order_id','appointment_id','title','shift_type','status','starts_at','ends_at','timezone','required_worker_count','required_skill_public_ids','instructions','created_at','updated_at']; }
}
