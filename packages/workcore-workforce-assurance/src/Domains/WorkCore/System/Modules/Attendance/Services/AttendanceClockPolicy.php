<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Attendance\Services;

use DateTimeImmutable;
use InvalidArgumentException;

final class AttendanceClockPolicy
{
    private const TRANSITIONS = [
        '' => ['clocked_in'],
        'clocked_in' => ['on_break', 'clocked_out', 'void'],
        'on_break' => ['clocked_in', 'clocked_out', 'void'],
        'clocked_out' => ['void'],
        'void' => [],
    ];

    public function assertTransition(?string $from, string $to): void
    {
        $key = $from ?? '';
        if (! in_array($to, self::TRANSITIONS[$key] ?? [], true)) {
            throw new InvalidArgumentException("Attendance status cannot move from ".($from ?? 'not_started')." to {$to}.");
        }
    }

    public function workedMinutes(string $startsAt, string $endsAt, int $breakMinutes = 0): int
    {
        $start = new DateTimeImmutable($startsAt);
        $end = new DateTimeImmutable($endsAt);
        if ($end <= $start) {
            throw new InvalidArgumentException('Clock-out time must be after clock-in time.');
        }
        return max(0, (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60) - max(0, $breakMinutes));
    }
}
