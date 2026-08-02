<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements;

final class AgreementState
{
    public const STATUSES = [
        'draft',
        'offered',
        'pending_signature',
        'active',
        'notice_given',
        'ending',
        'expired',
        'terminated',
        'cancelled',
        'superseded',
    ];

    public const INITIAL_STATUSES = [
        'draft',
        'offered',
        'pending_signature',
        'active',
    ];

    public const TERMINAL_STATUSES = [
        'expired',
        'terminated',
        'cancelled',
        'superseded',
    ];

    private const TRANSITIONS = [
        'draft' => ['offered', 'pending_signature', 'active', 'cancelled'],
        'offered' => ['pending_signature', 'active', 'cancelled'],
        'pending_signature' => ['active', 'cancelled'],
        'active' => ['notice_given', 'ending', 'expired', 'terminated', 'superseded'],
        'notice_given' => ['ending', 'expired', 'terminated', 'superseded'],
        'ending' => ['expired', 'terminated', 'superseded'],
        'expired' => [],
        'terminated' => [],
        'cancelled' => [],
        'superseded' => [],
    ];

    private const RENEWABLE_STATUSES = [
        'active',
        'notice_given',
        'ending',
        'expired',
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, self::INITIAL_STATUSES, true);
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::allowedTransitions($fromStatus), true);
    }

    public static function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canRenew(string $status): bool
    {
        return in_array($status, self::RENEWABLE_STATUSES, true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    public static function isCurrent(string $status): bool
    {
        return in_array($status, ['offered', 'pending_signature', 'active', 'notice_given', 'ending'], true);
    }
}
