<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Portfolio;

class ManagementAuthorityState
{
    public const STATUSES = [
        'draft', 'pending_approval', 'active', 'notice_given', 'suspended',
        'ended', 'rejected', 'cancelled',
    ];

    public const TERMINAL_STATUSES = ['ended', 'rejected', 'cancelled'];

    private const TRANSITIONS = [
        'draft' => ['pending_approval', 'cancelled'],
        'pending_approval' => ['active', 'rejected', 'cancelled'],
        'active' => ['notice_given', 'suspended', 'ended'],
        'notice_given' => ['active', 'suspended', 'ended'],
        'suspended' => ['active', 'ended'],
        'ended' => [],
        'rejected' => [],
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
