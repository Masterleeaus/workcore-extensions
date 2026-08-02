<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Services;

use InvalidArgumentException;

final class RiskMatrix
{
    /** @return array{score:int,level:string} */
    public function assess(int $likelihood, int $consequence): array
    {
        if ($likelihood < 1 || $likelihood > 5 || $consequence < 1 || $consequence > 5) {
            throw new InvalidArgumentException('Likelihood and consequence must each be between 1 and 5.');
        }

        $score = $likelihood * $consequence;
        $level = match (true) {
            $score <= 4 => 'low',
            $score <= 9 => 'medium',
            $score <= 16 => 'high',
            default => 'critical',
        };

        return ['score' => $score, 'level' => $level];
    }
}
