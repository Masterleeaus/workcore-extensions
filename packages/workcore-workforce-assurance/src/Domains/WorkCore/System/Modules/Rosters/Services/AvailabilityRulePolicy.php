<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Services;

use DateTimeImmutable;

final class AvailabilityRulePolicy
{
    /**
     * @param list<array<string,mixed>> $rules
     * @return array{available:bool,preferred:bool,on_call:bool,conflicts:list<string>}
     */
    public function evaluate(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, array $rules): array
    {
        $weekday = (int) $startsAt->format('N');
        $startTime = $startsAt->format('H:i:s');
        $endTime = $endsAt->format('H:i:s');
        $matchingAvailable = false;
        $preferred = false;
        $onCall = false;
        $conflicts = [];

        foreach ($rules as $rule) {
            if (isset($rule['weekday']) && $rule['weekday'] !== null && (int) $rule['weekday'] !== $weekday) {
                continue;
            }
            $ruleStart = (string) ($rule['starts_at'] ?? '00:00:00');
            $ruleEnd = (string) ($rule['ends_at'] ?? '23:59:59');
            $overlaps = $ruleStart < $endTime && $startTime < $ruleEnd;
            $contains = $ruleStart <= $startTime && $ruleEnd >= $endTime;
            $type = (string) ($rule['availability_type'] ?? 'available');

            if ($type === 'unavailable' && $overlaps) {
                $conflicts[] = 'Worker is unavailable during part of this shift.';
            }
            if ($contains && in_array($type, ['available', 'preferred', 'on_call'], true)) {
                $matchingAvailable = true;
                $preferred = $preferred || $type === 'preferred';
                $onCall = $onCall || $type === 'on_call';
            }
        }

        if (! $matchingAvailable) {
            $conflicts[] = 'Shift is outside the worker’s declared availability.';
        }

        return ['available' => $matchingAvailable && $conflicts === [], 'preferred' => $preferred, 'on_call' => $onCall, 'conflicts' => array_values(array_unique($conflicts))];
    }
}
