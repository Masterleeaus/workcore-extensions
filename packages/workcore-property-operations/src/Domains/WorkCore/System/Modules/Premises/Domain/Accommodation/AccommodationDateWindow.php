<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation;

use DateTimeImmutable;
use DomainException;

final class AccommodationDateWindow
{
    public static function overlaps(string $startA, string $endA, string $startB, string $endB): bool
    {
        [$aStart, $aEnd] = self::parse($startA, $endA);
        [$bStart, $bEnd] = self::parse($startB, $endB);

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    public static function nights(string $arrivalDate, string $departureDate): int
    {
        [$arrival, $departure] = self::parse($arrivalDate, $departureDate);
        $arrivalDay = $arrival->setTime(0, 0);
        $departureDay = $departure->setTime(0, 0);

        return (int) $arrivalDay->diff($departureDay)->days;
    }

    /** @return array{0:DateTimeImmutable,1:DateTimeImmutable} */
    private static function parse(string $start, string $end): array
    {
        try {
            $from = new DateTimeImmutable($start);
            $to = new DateTimeImmutable($end);
        } catch (\Throwable $exception) {
            throw new DomainException('Accommodation dates are invalid.', previous: $exception);
        }
        if ($to <= $from) {
            throw new DomainException('Accommodation departure must be after arrival.');
        }

        return [$from, $to];
    }
}
