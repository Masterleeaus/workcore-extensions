<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance;

final class ComplianceOccurrenceState
{
    public const STATUSES = ['pending', 'due', 'overdue', 'completed', 'waived', 'not_applicable'];
    public const TERMINAL = ['completed', 'waived', 'not_applicable'];

    private const TRANSITIONS = [
        'pending' => ['due', 'overdue', 'completed', 'waived', 'not_applicable'],
        'due' => ['overdue', 'completed', 'waived', 'not_applicable'],
        'overdue' => ['completed', 'waived', 'not_applicable'],
        'completed' => [],
        'waived' => [],
        'not_applicable' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }
}
