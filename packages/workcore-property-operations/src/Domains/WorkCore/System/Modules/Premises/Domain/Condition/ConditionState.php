<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Condition;

class ConditionState
{
    public const STATUSES = ['draft', 'in_progress', 'completed', 'approved', 'superseded', 'cancelled'];
    public const TERMINAL = ['approved', 'superseded', 'cancelled'];

    private const TRANSITIONS = [
        'draft' => ['in_progress', 'completed', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['approved', 'superseded'],
        'approved' => [],
        'superseded' => [],
        'cancelled' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, ['draft', 'in_progress', 'completed'], true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function allowedTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }
}
