<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Approvals;

class ApprovalRequestState
{
    public const STATUSES = [
        'draft', 'pending', 'approved', 'rejected', 'withdrawn',
        'expired', 'implemented', 'cancelled',
    ];

    public const TERMINAL_STATUSES = ['rejected', 'withdrawn', 'expired', 'implemented', 'cancelled'];

    private const TRANSITIONS = [
        'draft' => ['pending', 'withdrawn', 'cancelled'],
        'pending' => ['approved', 'rejected', 'withdrawn', 'expired', 'cancelled'],
        'approved' => ['implemented', 'cancelled'],
        'rejected' => [],
        'withdrawn' => [],
        'expired' => [],
        'implemented' => [],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }
}
