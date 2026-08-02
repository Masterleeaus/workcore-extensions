<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Access;

final class AccessCardRequestState
{
    public const STATUSES = [
        'draft', 'submitted', 'approved', 'partially_issued', 'issued',
        'closed', 'rejected', 'cancelled',
    ];

    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['partially_issued', 'issued', 'closed', 'cancelled'],
        'partially_issued' => ['issued', 'closed', 'cancelled'],
        'issued' => ['closed'],
        'closed' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, ['closed', 'rejected', 'cancelled'], true);
    }
}
