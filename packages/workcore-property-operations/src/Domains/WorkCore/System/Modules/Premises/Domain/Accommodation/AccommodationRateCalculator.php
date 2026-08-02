<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation;

use DomainException;

final class AccommodationRateCalculator
{
    public static function total(
        float $baseAmount,
        string $pricingBasis,
        int $nights,
        int $spaceCount,
        int $guestCount,
    ): float {
        if ($baseAmount < 0 || $nights < 1 || $spaceCount < 1 || $guestCount < 1) {
            throw new DomainException('Accommodation rate inputs are invalid.');
        }

        $units = match ($pricingBasis) {
            'night' => $nights * $spaceCount,
            'week' => (int) ceil($nights / 7) * $spaceCount,
            'month' => (int) ceil($nights / 30) * $spaceCount,
            'stay' => $spaceCount,
            'person_night', 'bed_night' => $nights * $guestCount,
            default => throw new DomainException("Unsupported accommodation pricing basis [{$pricingBasis}]."),
        };

        return round($baseAmount * $units, 4);
    }

    /** @return list<float> */
    public static function distribute(float $total, int $spaceCount): array
    {
        if ($total < 0 || $spaceCount < 1) {
            throw new DomainException('Accommodation rate distribution is invalid.');
        }
        $share = round($total / $spaceCount, 4);
        $amounts = array_fill(0, $spaceCount, $share);
        $amounts[$spaceCount - 1] = round($total - ($share * ($spaceCount - 1)), 4);

        return $amounts;
    }
}
