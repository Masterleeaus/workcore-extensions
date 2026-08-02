<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Compliance\Services;

use InvalidArgumentException;

final class ComplianceStatusPolicy
{
    private const ALLOWED = [
        'draft' => ['due', 'not_applicable', 'waived'],
        'due' => ['in_progress', 'compliant', 'non_compliant', 'waived', 'not_applicable'],
        'in_progress' => ['due', 'compliant', 'non_compliant', 'waived'],
        'overdue' => ['in_progress', 'compliant', 'non_compliant', 'waived'],
        'compliant' => ['due', 'in_progress', 'non_compliant', 'expired'],
        'non_compliant' => ['in_progress', 'compliant', 'waived'],
        'expired' => ['in_progress', 'compliant', 'waived'],
        'waived' => ['due', 'in_progress'],
        'not_applicable' => ['due'],
    ];

    public function derive(?string $completedOn, ?string $dueOn, ?string $today = null): string
    {
        if ($completedOn !== null && $completedOn !== '') {
            return 'compliant';
        }
        if ($dueOn !== null && $dueOn !== '' && strtotime($dueOn) < strtotime($today ?: date('Y-m-d'))) {
            return 'overdue';
        }
        return 'due';
    }

    public function assertTransition(string $from, string $to, ?string $reason): void
    {
        if ($from === $to) {
            return;
        }
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new InvalidArgumentException("Unsupported compliance transition from {$from} to {$to}.");
        }
        if (in_array($to, ['waived', 'not_applicable'], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when waiving or marking an obligation not applicable.');
        }
    }
}
