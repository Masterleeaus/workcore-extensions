<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Portal;

final class PortalGrantState
{
    public const STATUSES = ['invited', 'active', 'expired', 'revoked'];
    public const TERMINAL_STATUSES = ['expired', 'revoked'];

    private const TRANSITIONS = [
        'invited' => ['active', 'expired', 'revoked'],
        'active' => ['expired', 'revoked'],
        'expired' => [],
        'revoked' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return $status === 'invited';
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
