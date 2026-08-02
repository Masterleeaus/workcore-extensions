<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation;

use DomainException;

final class AccommodationReservationState
{
    public const STATUSES = [
        'draft', 'tentative', 'confirmed', 'checked_in', 'checked_out',
        'cancelled', 'no_show', 'expired',
    ];

    public const CAPACITY_CONSUMING = ['tentative', 'confirmed', 'checked_in'];

    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'draft' => ['tentative', 'confirmed', 'cancelled', 'expired'],
        'tentative' => ['confirmed', 'cancelled', 'expired'],
        'confirmed' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
        'no_show' => [],
        'expired' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! in_array($from, self::STATUSES, true) || ! in_array($to, self::STATUSES, true)) {
            throw new DomainException('Unknown accommodation reservation status.');
        }
        if (! self::canTransition($from, $to)) {
            throw new DomainException("Accommodation reservation cannot transition from [{$from}] to [{$to}].");
        }
    }

    public static function isCapacityConsuming(string $status): bool
    {
        return in_array($status, self::CAPACITY_CONSUMING, true);
    }
}
