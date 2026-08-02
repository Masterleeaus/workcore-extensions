<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation;

use DomainException;

final class HousekeepingState
{
    public const STATUSES = ['dirty', 'assigned', 'in_progress', 'cleaned', 'inspected', 'ready', 'blocked', 'cancelled'];

    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'dirty' => ['assigned', 'in_progress', 'blocked', 'cancelled'],
        'assigned' => ['in_progress', 'blocked', 'cancelled'],
        'in_progress' => ['cleaned', 'blocked', 'cancelled'],
        'cleaned' => ['inspected', 'ready', 'blocked'],
        'inspected' => ['ready', 'in_progress', 'blocked'],
        'ready' => ['dirty', 'blocked'],
        'blocked' => ['assigned', 'in_progress', 'cancelled'],
        'cancelled' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new DomainException("Housekeeping task cannot transition from [{$from}] to [{$to}].");
        }
    }
}
