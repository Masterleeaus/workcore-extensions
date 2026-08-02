<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\WorkOrderAccessContract;

final class WorkOrderAccessAdapter extends AbstractDatabaseRecordAccess implements WorkOrderAccessContract
{
    protected function table(): string
    {
        return 'tz_work_orders';
    }

    protected function type(): string
    {
        return 'work_order';
    }

    protected function columns(): array
    {
        return [
            'public_id', 'number', 'customer_id', 'premises_id', 'title', 'description', 'priority',
            'status', 'source', 'due_at', 'started_at', 'completed_at', 'cancelled_at', 'created_at', 'updated_at',
        ];
    }
}
