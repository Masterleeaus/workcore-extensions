<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Services;

use InvalidArgumentException;

final class WorkOrderTaskStatusPolicy
{
    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        'pending' => ['in_progress', 'completed', 'skipped'],
        'in_progress' => ['pending', 'completed', 'skipped'],
        'completed' => ['in_progress'],
        'skipped' => ['pending', 'in_progress'],
    ];

    public function assertTransition(string $fromStatus, string $toStatus): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }
        if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw new InvalidArgumentException("Task cannot move from {$fromStatus} to {$toStatus}.");
        }
    }
}
