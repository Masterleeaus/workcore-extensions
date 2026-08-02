<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Requests;

final class ServiceRequestState
{
    public const STATUSES = ['submitted', 'triaged', 'work_requested', 'in_progress', 'resolved', 'closed', 'cancelled'];
    private const TRANSITIONS = [
        'submitted' => ['triaged', 'cancelled'],
        'triaged' => ['work_requested', 'in_progress', 'resolved', 'cancelled'],
        'work_requested' => ['in_progress', 'resolved', 'cancelled'],
        'in_progress' => ['resolved', 'cancelled'],
        'resolved' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];
    public static function canTransition(string $from, string $to): bool { return in_array($to, self::TRANSITIONS[$from] ?? [], true); }
    public static function isTerminal(string $status): bool { return in_array($status, ['closed', 'cancelled'], true); }
}
