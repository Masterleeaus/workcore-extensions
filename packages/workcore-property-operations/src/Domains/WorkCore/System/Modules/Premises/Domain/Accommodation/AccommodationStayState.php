<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation;

use DomainException;

final class AccommodationStayState
{
    public const STATUSES = ['expected', 'in_house', 'checked_out', 'cancelled'];

    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'expected' => ['in_house', 'cancelled'],
        'in_house' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new DomainException("Accommodation stay cannot transition from [{$from}] to [{$to}].");
        }
    }
}
