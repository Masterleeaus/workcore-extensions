<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Operations;

final class WorkflowState
{
    public const STATUSES = ['active', 'paused', 'completed', 'cancelled'];
    public const INITIAL_STATUSES = ['active', 'paused'];
    public const TERMINAL_STATUSES = ['completed', 'cancelled'];

    private const TRANSITIONS = [
        'active' => ['paused', 'completed', 'cancelled'],
        'paused' => ['active', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, self::INITIAL_STATUSES, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }
}
