<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Services;

final class InspectionScoringPolicy
{
    /** @return array{score:float,outcome:string,failures:int,critical_failures:int,applicable_items:int} */
    public function score(array $items): array
    {
        $possible = 0.0;
        $earned = 0.0;
        $failures = 0;
        $criticalFailures = 0;
        $applicable = 0;

        foreach ($items as $item) {
            $result = strtolower(trim((string) ($item['result'] ?? '')));
            if ($result === 'na' || $result === 'not_applicable') {
                continue;
            }

            $weight = max(0.0, (float) ($item['weight'] ?? 1));
            $possible += $weight;
            $applicable++;

            if ($result === 'pass') {
                $earned += $weight;
                continue;
            }

            if ($result === 'fail') {
                $failures++;
                if (strtolower((string) ($item['severity'] ?? 'medium')) === 'critical') {
                    $criticalFailures++;
                }
            }
        }

        $score = $possible > 0 ? round(($earned / $possible) * 100, 2) : 100.0;
        $outcome = $criticalFailures > 0 ? 'fail' : ($failures > 0 ? 'conditional' : 'pass');

        return [
            'score' => $score,
            'outcome' => $outcome,
            'failures' => $failures,
            'critical_failures' => $criticalFailures,
            'applicable_items' => $applicable,
        ];
    }
}
