<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Services;

use InvalidArgumentException;

final class AppointmentStatusPolicy
{
    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        'tentative' => ['scheduled', 'confirmed', 'cancelled'],
        'scheduled' => ['confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'],
        'confirmed' => ['in_progress', 'completed', 'cancelled', 'no_show'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['tentative', 'scheduled'],
        'no_show' => ['scheduled'],
    ];

    public function assertTransition(string $fromStatus, string $toStatus, ?string $reason): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw new InvalidArgumentException("Appointment cannot move from {$fromStatus} to {$toStatus}.");
        }

        if (($toStatus === 'cancelled' || $toStatus === 'no_show' || in_array($fromStatus, ['cancelled', 'no_show'], true))
            && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when cancelling, marking no-show, or reopening an appointment.');
        }
    }
}
