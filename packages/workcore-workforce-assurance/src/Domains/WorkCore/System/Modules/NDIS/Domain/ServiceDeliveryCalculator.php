<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Domain;

use DomainException;

final class ServiceDeliveryCalculator
{
    public static function quantity(int $minutes, string $unit, ?float $explicitQuantity = null): float
    {
        if ($minutes < 0) throw new DomainException('Service minutes cannot be negative.');
        if ($explicitQuantity !== null) {
            if ($explicitQuantity < 0) throw new DomainException('Service quantity cannot be negative.');
            return round($explicitQuantity, 4);
        }
        return match ($unit) {
            'hour' => round($minutes / 60, 4),
            'minute' => round((float) $minutes, 4),
            'each', 'day', 'week', 'month', 'kilometre' => $minutes > 0 ? 1.0 : 0.0,
            default => throw new DomainException("Unsupported service unit [{$unit}]."),
        };
    }

    public static function amount(float $quantity, float $unitRate): float
    {
        if ($quantity < 0 || $unitRate < 0) throw new DomainException('Service quantity and rate cannot be negative.');
        return round($quantity * $unitRate, 4);
    }

    public static function claimableMinutes(int $scheduledMinutes, string $outcome, int $configuredCancellationMinutes = 0): int
    {
        if ($scheduledMinutes < 0 || $configuredCancellationMinutes < 0) throw new DomainException('Claimable minutes cannot be negative.');
        return match ($outcome) {
            'delivered', 'participant_no_show' => $scheduledMinutes,
            'late_cancellation' => min($scheduledMinutes, $configuredCancellationMinutes),
            'provider_cancelled', 'not_delivered' => 0,
            default => throw new DomainException("Unsupported delivery outcome [{$outcome}]."),
        };
    }
}
