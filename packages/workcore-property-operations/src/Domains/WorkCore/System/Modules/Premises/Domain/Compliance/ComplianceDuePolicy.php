<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance;

use DateTimeImmutable;

final class ComplianceDuePolicy
{
    public static function statusFor(
        DateTimeImmutable $dueAt,
        DateTimeImmutable $now,
        int $warningDays = 30,
        int $graceDays = 0
    ): string {
        $warningDays = max(0, $warningDays);
        $graceDays = max(0, $graceDays);
        $overdueAt = $dueAt->modify('+' . $graceDays . ' days')->setTime(23, 59, 59);

        if ($now > $overdueAt) {
            return 'overdue';
        }

        $warningAt = $dueAt->modify('-' . $warningDays . ' days')->setTime(0, 0, 0);
        return $now >= $warningAt ? 'due' : 'pending';
    }
}
