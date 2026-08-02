<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\AppointmentAccessContract;

final class AppointmentAccessAdapter extends AbstractDatabaseRecordAccess implements AppointmentAccessContract
{
    protected function table(): string
    {
        return 'tz_appointments';
    }

    protected function type(): string
    {
        return 'appointment';
    }

    protected function columns(): array
    {
        return [
            'public_id', 'work_order_id', 'customer_id', 'premises_id', 'title', 'description',
            'appointment_type', 'status', 'starts_at', 'ends_at', 'timezone', 'all_day',
            'access_notes', 'internal_notes', 'source', 'created_at', 'updated_at',
        ];
    }
}
