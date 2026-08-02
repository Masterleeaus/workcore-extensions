<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Procurement;

use InvalidArgumentException;

final class PurchaseOrderLine
{
    private int $receivedQuantity = 0;

    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly int $quantity,
        public readonly int $unitPriceMinor,
        public readonly int $taxMinor = 0,
        public readonly ?string $inventoryItemId = null,
    ) {
        if (trim($id) === '' || trim($description) === '' || $quantity <= 0 || $unitPriceMinor < 0 || $taxMinor < 0) {
            throw new InvalidArgumentException('A valid purchase order line is required.');
        }
    }

    public function totalMinor(): int
    {
        return ($this->quantity * $this->unitPriceMinor) + $this->taxMinor;
    }

    public function receive(int $quantity): void
    {
        if ($quantity <= 0 || $this->receivedQuantity + $quantity > $this->quantity) {
            throw new InvalidArgumentException('Receipt quantity must be positive and cannot exceed ordered quantity.');
        }
        $this->receivedQuantity += $quantity;
    }

    public function isFullyReceived(): bool
    {
        return $this->receivedQuantity === $this->quantity;
    }
}
