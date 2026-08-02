<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Access;

class AccessItemState
{
    public const STATUSES = ['available', 'issued', 'returned', 'lost', 'revoked', 'expired', 'decommissioned'];
    public const TERMINAL = ['revoked', 'expired', 'decommissioned'];

    private const TRANSITIONS = [
        'available' => ['issued', 'lost', 'revoked', 'expired', 'decommissioned'],
        'issued' => ['returned', 'lost', 'revoked', 'expired'],
        'returned' => ['available', 'issued', 'lost', 'revoked', 'decommissioned'],
        'lost' => ['available', 'revoked', 'decommissioned'],
        'revoked' => [],
        'expired' => ['decommissioned'],
        'decommissioned' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, ['available', 'issued'], true);
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
