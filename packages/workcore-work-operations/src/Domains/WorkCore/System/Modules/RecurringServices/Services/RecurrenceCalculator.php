<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\RecurringServices\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class RecurrenceCalculator
{
    /** @return list<DateTimeImmutable> */
    public function between(array $rule, string $fromOn, string $toOn): array
    {
        $frequency = (string) ($rule['frequency'] ?? 'monthly');
        if (! in_array($frequency, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'], true)) {
            throw new InvalidArgumentException('Unsupported recurring-service frequency.');
        }
        $interval = max(1, (int) ($rule['interval_count'] ?? 1));
        $timezone = new DateTimeZone((string) ($rule['timezone'] ?? 'Australia/Melbourne'));
        $ruleStart = new DateTimeImmutable((string) $rule['starts_on'] . ' 00:00:00', $timezone);
        $from = new DateTimeImmutable($fromOn . ' 00:00:00', $timezone);
        $to = new DateTimeImmutable($toOn . ' 23:59:59', $timezone);
        if (! empty($rule['ends_on'])) {
            $ruleEnd = new DateTimeImmutable((string) $rule['ends_on'] . ' 23:59:59', $timezone);
            if ($ruleEnd < $to) $to = $ruleEnd;
        }
        if ($from < $ruleStart) $from = $ruleStart;
        if ($from > $to) return [];

        $time = (string) ($rule['time_of_day'] ?? '09:00:00');
        $weekdays = array_values(array_unique(array_map('intval', (array) ($rule['weekdays'] ?? []))));
        if ($weekdays === []) $weekdays = [(int) $ruleStart->format('N')];
        $dayOfMonth = max(1, min(31, (int) ($rule['day_of_month'] ?? $ruleStart->format('j'))));
        $monthOfYear = max(1, min(12, (int) ($rule['month_of_year'] ?? $ruleStart->format('n'))));

        $result = [];
        for ($cursor = $from; $cursor <= $to; $cursor = $cursor->add(new DateInterval('P1D'))) {
            if (! $this->matches($cursor, $ruleStart, $frequency, $interval, $weekdays, $dayOfMonth, $monthOfYear)) continue;
            [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
            $result[] = $cursor->setTime($hour, $minute, $second);
        }
        return $result;
    }

    private function matches(DateTimeImmutable $date, DateTimeImmutable $start, string $frequency, int $interval, array $weekdays, int $dayOfMonth, int $monthOfYear): bool
    {
        $days = (int) $start->diff($date)->format('%a');
        if ($date < $start) return false;
        if ($frequency === 'daily') return $days % $interval === 0;
        if ($frequency === 'weekly') {
            $startMonday = $start->modify('monday this week');
            $dateMonday = $date->modify('monday this week');
            $weeks = intdiv((int) $startMonday->diff($dateMonday)->format('%a'), 7);
            return $weeks % $interval === 0 && in_array((int) $date->format('N'), $weekdays, true);
        }
        $months = (((int) $date->format('Y') - (int) $start->format('Y')) * 12) + ((int) $date->format('n') - (int) $start->format('n'));
        $targetDay = min($dayOfMonth, (int) $date->format('t'));
        if ($frequency === 'monthly') return $months % $interval === 0 && (int) $date->format('j') === $targetDay;
        if ($frequency === 'quarterly') return $months % ($interval * 3) === 0 && (int) $date->format('j') === $targetDay;
        $years = (int) $date->format('Y') - (int) $start->format('Y');
        return $years % $interval === 0 && (int) $date->format('n') === $monthOfYear && (int) $date->format('j') === $targetDay;
    }
}
