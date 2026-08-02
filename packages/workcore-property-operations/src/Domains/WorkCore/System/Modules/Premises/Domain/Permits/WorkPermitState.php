<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Permits;

final class WorkPermitState
{
    public const STATUSES = [
        'draft', 'submitted', 'manager_approved', 'site_approved', 'validated',
        'active', 'suspended', 'completed', 'expired', 'rejected', 'cancelled',
    ];

    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['manager_approved', 'rejected', 'cancelled'],
        'manager_approved' => ['site_approved', 'rejected', 'cancelled'],
        'site_approved' => ['validated', 'rejected', 'cancelled'],
        'validated' => ['active', 'suspended', 'cancelled', 'expired'],
        'active' => ['suspended', 'completed', 'expired'],
        'suspended' => ['active', 'completed', 'expired', 'cancelled'],
        'completed' => [],
        'expired' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, ['completed', 'expired', 'rejected', 'cancelled'], true);
    }
}
