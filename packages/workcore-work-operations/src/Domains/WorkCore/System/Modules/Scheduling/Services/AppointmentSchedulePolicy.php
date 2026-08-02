<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final class AppointmentSchedulePolicy
{
    /** @return array{starts_at:DateTimeImmutable,ends_at:DateTimeImmutable,timezone:string} */
    public function normalise(string $startsAt, string $endsAt, string $timezone): array
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            throw new InvalidArgumentException('Appointment timezone is required.');
        }

        try {
            $zone = new DateTimeZone($timezone);
            $start = new DateTimeImmutable($startsAt, $zone);
            $end = new DateTimeImmutable($endsAt, $zone);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Appointment dates or timezone are invalid.', previous: $exception);
        }

        if ($end <= $start) {
            throw new InvalidArgumentException('Appointment end must be after its start.');
        }

        if (($end->getTimestamp() - $start->getTimestamp()) > 604800) {
            throw new InvalidArgumentException('An appointment cannot exceed seven days.');
        }

        $utc = new DateTimeZone('UTC');

        return [
            'starts_at' => $start->setTimezone($utc),
            'ends_at' => $end->setTimezone($utc),
            'timezone' => $timezone,
        ];
    }
}
