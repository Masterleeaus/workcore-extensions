<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Attendance\Services;

use InvalidArgumentException;

final class TimesheetStatusPolicy
{
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['approved', 'rejected'],
        'approved' => ['locked'],
        'rejected' => ['submitted'],
        'locked' => [],
    ];

    public function assertTransition(string $from, string $to, ?string $reason = null): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("Timesheet cannot move from {$from} to {$to}.");
        }
        if ($to === 'rejected' && trim((string) $reason) === '') {
            throw new InvalidArgumentException('Rejecting a timesheet requires a reason.');
        }
    }
}
