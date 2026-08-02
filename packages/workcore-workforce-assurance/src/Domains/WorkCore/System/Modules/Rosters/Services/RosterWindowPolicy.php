<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Services;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

final class RosterWindowPolicy
{
    /** @return array{starts_at:DateTimeImmutable,ends_at:DateTimeImmutable,timezone:string} */
    public function normalise(string $startsAt, string $endsAt, string $timezone): array
    {
        try {
            $zone = new DateTimeZone($timezone);
            $start = new DateTimeImmutable($startsAt, $zone);
            $end = new DateTimeImmutable($endsAt, $zone);
        } catch (Exception $exception) {
            throw new InvalidArgumentException('Roster shift contains an invalid date, time, or timezone.', previous: $exception);
        }
        if ($end <= $start) {
            throw new InvalidArgumentException('Roster shift end must be after its start.');
        }
        if (($end->getTimestamp() - $start->getTimestamp()) > 86400 * 7) {
            throw new InvalidArgumentException('A single roster shift cannot exceed seven days.');
        }
        $utc = new DateTimeZone('UTC');
        return ['starts_at' => $start->setTimezone($utc), 'ends_at' => $end->setTimezone($utc), 'timezone' => $timezone];
    }
}
