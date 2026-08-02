<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DomainException;
use InvalidArgumentException;

final readonly class InvoiceLineDraft
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id,
        public int $position,
        public string $description,
        public int $quantityScaled,
        public int $quantityScale,
        public Money $unitPrice,
        public Money $subtotal,
        public Money $discount,
        public Money $tax,
        public Money $total,
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public ?string $taxCode = null,
        public int $taxRateBasisPoints = 0,
        public array $metadata = [],
    ) {
        if (trim($id) === '' || trim($description) === '') {
            throw new InvalidArgumentException('Invoice line identity and description are required.');
        }
        if ($position < 1 || $quantityScaled <= 0 || $quantityScale < 0 || $quantityScale > 6) {
            throw new InvalidArgumentException('Invoice line quantity and position are invalid.');
        }
        if ($taxRateBasisPoints < 0 || $taxRateBasisPoints > 10000) {
            throw new InvalidArgumentException('Tax rate basis points must be between 0 and 10000.');
        }
        $currency = $unitPrice->currency;
        foreach ([$subtotal, $discount, $tax, $total] as $amount) {
            if (! $currency->equals($amount->currency)) {
                throw new DomainException('Invoice line amounts must use one currency.');
            }
            if ($amount->isNegative()) {
                throw new DomainException('Invoice line amounts cannot be negative.');
            }
        }
        if (! $subtotal->subtract($discount)->add($tax)->equals($total)) {
            throw new DomainException('Invoice line totals do not balance.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'description' => $this->description,
            'quantity_scaled' => $this->quantityScaled,
            'quantity_scale' => $this->quantityScale,
            'unit_price' => $this->unitPrice->toArray(),
            'subtotal' => $this->subtotal->toArray(),
            'discount' => $this->discount->toArray(),
            'tax' => $this->tax->toArray(),
            'total' => $this->total->toArray(),
            'tax_code' => $this->taxCode,
            'tax_rate_basis_points' => $this->taxRateBasisPoints,
            'metadata' => $this->metadata,
        ];
    }
}
