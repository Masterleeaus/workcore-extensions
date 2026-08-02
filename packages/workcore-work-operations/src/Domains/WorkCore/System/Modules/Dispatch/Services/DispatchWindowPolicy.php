<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DispatchWindowPolicy
{
    /** @return array{scheduled_start:DateTimeImmutable,scheduled_end:DateTimeImmutable,timezone:string} */
    public function normalise(string $start, string $end, string $timezone): array
    {
        try {
            $zone = new DateTimeZone($timezone);
            $localStart = new DateTimeImmutable($start, $zone);
            $localEnd = new DateTimeImmutable($end, $zone);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('The dispatch window or timezone is invalid.', previous: $exception);
        }
        if ($localEnd <= $localStart) {
            throw new InvalidArgumentException('Dispatch end time must be after its start time.');
        }
        $utc = new DateTimeZone('UTC');
        return [
            'scheduled_start' => $localStart->setTimezone($utc),
            'scheduled_end' => $localEnd->setTimezone($utc),
            'timezone' => $timezone,
        ];
    }
}
