<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Services;

use InvalidArgumentException;

final class InventoryQuantityPolicy
{
    public function assertPositive(float $quantity, string $field = 'quantity'): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("{$field} must be greater than zero.");
        }
    }

    public function available(float $onHand, float $reserved): float
    {
        return round($onHand - $reserved, 4);
    }

    public function remaining(float $reserved, float $consumed): float
    {
        return max(0.0, round($reserved - $consumed, 4));
    }

    public function assertAvailable(float $onHand, float $reserved, float $requested): void
    {
        $this->assertPositive($requested, 'requested quantity');
        $available = $this->available($onHand, $reserved);
        if ($requested > $available + 0.00001) {
            throw new InvalidArgumentException("Insufficient available stock. Requested {$requested}; available {$available}.");
        }
    }

    public function assertReservationConsumption(float $reserved, float $consumed, float $requested): void
    {
        $this->assertPositive($requested, 'consumption quantity');
        $remaining = $this->remaining($reserved, $consumed);
        if ($requested > $remaining + 0.00001) {
            throw new InvalidArgumentException("Consumption exceeds the remaining reservation quantity of {$remaining}.");
        }
    }
}
