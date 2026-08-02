<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Services;

use InvalidArgumentException;

final class StockMovementPolicy
{
    private const TYPES = ['receipt', 'transfer', 'adjustment', 'issue', 'return', 'consumption', 'waste'];

    public function assertQuantity(string $type, float $quantity): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported stock movement type.');
        }
        if ($type === 'adjustment') {
            if (abs($quantity) < 0.00001) {
                throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
            }
            return;
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock movement quantity must be greater than zero.');
        }
    }

    public function assertShape(string $type, ?string $source, ?string $target): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported stock movement type.');
        }
        if ($type === 'transfer' && ($source === null || $target === null)) {
            throw new InvalidArgumentException('Transfers require source and destination stock locations.');
        }
        if (in_array($type, ['issue', 'consumption', 'waste'], true) && $source === null) {
            throw new InvalidArgumentException("{$type} movements require a source stock location.");
        }
        if (in_array($type, ['receipt', 'return', 'adjustment'], true) && $target === null) {
            throw new InvalidArgumentException("{$type} movements require a destination stock location.");
        }
        if ($source !== null && $target !== null && $source === $target) {
            throw new InvalidArgumentException('Source and destination stock locations must differ.');
        }
    }
}
