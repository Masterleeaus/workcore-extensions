<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Compliance\Services;

use InvalidArgumentException;

final class SafetySeverityPolicy
{
    public function priority(string $severity): string
    {
        return match ($severity) {
            'life_safety' => 'emergency',
            'critical' => 'urgent',
            'high' => 'high',
            'medium' => 'normal',
            'low' => 'low',
            default => throw new InvalidArgumentException('Unsupported safety severity.'),
        };
    }

    public function assertClosure(string $from, string $to, ?string $reason): void
    {
        if (in_array($to, ['resolved', 'closed', 'accepted'], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A closure reason is required for a safety record.');
        }
        if ($from === 'closed' && $to !== 'reopened') {
            throw new InvalidArgumentException('Closed safety records may only be reopened.');
        }
    }
}
