<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Domain;

use DomainException;

final class ParticipantStatus
{
    private const TRANSITIONS = [
        'prospective' => ['active', 'declined'],
        'active' => ['suspended', 'inactive', 'deceased'],
        'suspended' => ['active', 'inactive'],
        'declined' => [],
        'inactive' => [],
        'deceased' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertAllowed(string $status): void
    {
        if (! array_key_exists($status, self::TRANSITIONS)) throw new DomainException("Unsupported participant status [{$status}].");
    }
}
