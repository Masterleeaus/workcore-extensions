<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Domain;

use DomainException;

final class ClaimLineState
{
    private const TRANSITIONS = [
        'prepared' => ['held', 'exported', 'voided'],
        'held' => ['prepared', 'voided'],
        'exported' => ['accepted', 'rejected', 'reconciled', 'voided'],
        'accepted' => ['reconciled'],
        'rejected' => ['prepared', 'voided'],
        'reconciled' => [], 'voided' => [],
    ];
    public static function canTransition(string $from, string $to): bool { return in_array($to, self::TRANSITIONS[$from] ?? [], true); }
    public static function assertAllowed(string $status): void { if (! array_key_exists($status, self::TRANSITIONS)) throw new DomainException("Unsupported claim-line status [{$status}]."); }
}
