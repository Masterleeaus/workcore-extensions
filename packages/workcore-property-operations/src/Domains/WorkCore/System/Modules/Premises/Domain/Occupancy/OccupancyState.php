<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy;

final class OccupancyState
{
    public const STATUSES = [
        'applicant',
        'reserved',
        'pending',
        'active',
        'notice_given',
        'ending',
        'suspended',
        'vacated',
        'cancelled',
    ];

    public const INITIAL_STATUSES = [
        'applicant',
        'reserved',
        'pending',
        'active',
    ];

    public const CAPACITY_CONSUMING = [
        'reserved',
        'pending',
        'active',
        'notice_given',
        'ending',
        'suspended',
    ];

    private const TRANSITIONS = [
        'applicant' => ['reserved', 'pending', 'cancelled'],
        'reserved' => ['pending', 'active', 'suspended', 'cancelled'],
        'pending' => ['active', 'suspended', 'cancelled'],
        'active' => ['notice_given', 'ending', 'suspended', 'vacated'],
        'notice_given' => ['ending', 'suspended', 'vacated'],
        'ending' => ['suspended', 'vacated'],
        'suspended' => ['active', 'notice_given', 'ending', 'vacated', 'cancelled'],
        'vacated' => [],
        'cancelled' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, self::INITIAL_STATUSES, true);
    }

    public static function consumesCapacity(string $status): bool
    {
        return in_array($status, self::CAPACITY_CONSUMING, true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        if (!in_array($from, self::STATUSES, true) || !in_array($to, self::STATUSES, true)) {
            return false;
        }

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function allowedTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, ['vacated', 'cancelled'], true);
    }
}
