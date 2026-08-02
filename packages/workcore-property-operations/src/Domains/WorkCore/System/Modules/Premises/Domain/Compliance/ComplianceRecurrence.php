<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance;

use DateTimeImmutable;
use InvalidArgumentException;

final class ComplianceRecurrence
{
    public const TYPES = ['none', 'days', 'months', 'years', 'annual'];

    public static function nextDueDate(
        DateTimeImmutable $from,
        string $type,
        ?int $interval = null,
        ?int $annualMonth = null,
        ?int $annualDay = null
    ): ?DateTimeImmutable {
        if ($type === 'none') {
            return null;
        }

        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported recurrence type: {$type}");
        }

        return match ($type) {
            'days' => $from->modify('+' . max(1, (int) $interval) . ' days'),
            'months' => self::addMonthsClamped($from, max(1, (int) $interval)),
            'years' => self::addYearsClamped($from, max(1, (int) $interval)),
            'annual' => self::nextAnnualDate($from, (int) $annualMonth, (int) $annualDay),
            default => null,
        };
    }

    private static function addMonthsClamped(DateTimeImmutable $from, int $months): DateTimeImmutable
    {
        $year = (int) $from->format('Y');
        $month = (int) $from->format('n');
        $day = (int) $from->format('j');
        $total = ($year * 12 + ($month - 1)) + $months;
        $targetYear = intdiv($total, 12);
        $targetMonth = ($total % 12) + 1;
        $targetDay = min($day, self::daysInMonth($targetYear, $targetMonth));

        return $from->setDate($targetYear, $targetMonth, $targetDay);
    }

    private static function addYearsClamped(DateTimeImmutable $from, int $years): DateTimeImmutable
    {
        $targetYear = (int) $from->format('Y') + $years;
        $month = (int) $from->format('n');
        $day = min((int) $from->format('j'), self::daysInMonth($targetYear, $month));

        return $from->setDate($targetYear, $month, $day);
    }

    private static function daysInMonth(int $year, int $month): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
            ->modify('last day of this month')
            ->format('j');
    }

    private static function nextAnnualDate(DateTimeImmutable $from, int $month, int $day): DateTimeImmutable
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw new InvalidArgumentException('Annual recurrence requires a valid month and day.');
        }

        $year = (int) $from->format('Y');
        $candidateDay = min($day, self::daysInMonth($year, $month));
        $candidate = $from->setDate($year, $month, $candidateDay);

        if ($candidate <= $from) {
            $year++;
            $candidateDay = min($day, self::daysInMonth($year, $month));
            $candidate = $from->setDate($year, $month, $candidateDay);
        }

        return $candidate;
    }
}
