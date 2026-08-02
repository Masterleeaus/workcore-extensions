<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\TaskAccessContract;

final class TaskAccessAdapter extends AbstractDatabaseRecordAccess implements TaskAccessContract
{
    protected function table(): string
    {
        return 'tz_work_order_tasks';
    }

    protected function type(): string
    {
        return 'task';
    }

    protected function columns(): array
    {
        return [
            'public_id', 'work_order_id', 'work_order_service_id', 'name', 'description', 'sort_order',
            'estimated_minutes', 'is_required', 'status', 'completed_at', 'completed_by_user_id',
        ];
    }
}
