<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\AvailabilityAccessContract;

final class AvailabilityAccessAdapter extends AbstractDatabaseRecordAccess implements AvailabilityAccessContract
{
    protected function table(): string { return 'tz_worker_availability_rules'; }
    protected function type(): string { return 'availability_rule'; }
    protected function columns(): array { return ['public_id','worker_id','availability_type','recurrence_type','weekday','starts_at','ends_at','effective_from','effective_to','timezone','source','notes','created_at','updated_at']; }
}
