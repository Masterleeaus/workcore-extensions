<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Incidents;

class IncidentState
{
    public const STATUSES = ['open', 'triaged', 'investigating', 'action_required', 'work_requested', 'resolved', 'closed', 'cancelled'];
    public const TERMINAL = ['closed', 'cancelled'];

    private const TRANSITIONS = [
        'open' => ['triaged', 'investigating', 'action_required', 'work_requested', 'resolved', 'cancelled'],
        'triaged' => ['investigating', 'action_required', 'work_requested', 'resolved', 'cancelled'],
        'investigating' => ['action_required', 'work_requested', 'resolved', 'cancelled'],
        'action_required' => ['work_requested', 'resolved', 'cancelled'],
        'work_requested' => ['investigating', 'action_required', 'resolved', 'cancelled'],
        'resolved' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, ['open', 'triaged'], true);
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
