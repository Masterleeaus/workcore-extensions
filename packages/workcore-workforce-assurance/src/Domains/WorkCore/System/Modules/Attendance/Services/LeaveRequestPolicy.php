<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Attendance\Services;

use DateTimeImmutable;
use InvalidArgumentException;

final class LeaveRequestPolicy
{
    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'declined', 'cancelled'],
        'approved' => ['cancelled'],
        'declined' => ['submitted'],
        'cancelled' => [],
    ];

    public function assertTransition(string $from, string $to, ?string $reason): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("Leave request cannot move from {$from} to {$to}.");
        }
        if (in_array($to, ['declined', 'cancelled'], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException(ucfirst($to).' leave requires a reason.');
        }
    }

    public function requestedMinutes(string $startsAt, string $endsAt): int
    {
        $start = new DateTimeImmutable($startsAt);
        $end = new DateTimeImmutable($endsAt);
        if ($end <= $start) {
            throw new InvalidArgumentException('Leave end must be after leave start.');
        }
        return (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }
}
