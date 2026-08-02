<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Sales;

use InvalidArgumentException;

final readonly class SaleLine
{
    public function __construct(
        public string $id,
        public string $description,
        public int $quantity,
        public int $unitPriceMinor,
        public int $taxMinor = 0,
        public ?string $productId = null,
    ) {
        if (trim($id) === '' || trim($description) === '' || $quantity <= 0 || $unitPriceMinor < 0 || $taxMinor < 0) {
            throw new InvalidArgumentException('A valid sale line is required.');
        }
    }

    public function totalMinor(): int
    {
        return ($this->quantity * $this->unitPriceMinor) + $this->taxMinor;
    }
}
