<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Procurement;

use DomainException;
use InvalidArgumentException;

final class PurchaseOrder
{
    private PurchaseOrderState $state = PurchaseOrderState::Draft;
    private ?string $approvedBy = null;

    /** @param list<PurchaseOrderLine> $lines */
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $supplierId,
        public readonly string $currencyCode,
        public readonly array $lines,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($supplierId) === '' || $lines === []) {
            throw new InvalidArgumentException('Purchase order identity, company, supplier and lines are required.');
        }
        foreach ($lines as $line) {
            if (! $line instanceof PurchaseOrderLine) {
                throw new InvalidArgumentException('Every purchase order line must be a PurchaseOrderLine.');
            }
        }
    }

    public function state(): PurchaseOrderState
    {
        return $this->state;
    }

    public function totalMinor(): int
    {
        return array_sum(array_map(static fn (PurchaseOrderLine $line): int => $line->totalMinor(), $this->lines));
    }

    public function submit(): void
    {
        $this->transition(PurchaseOrderState::Draft, PurchaseOrderState::Submitted);
    }

    public function approve(string $actorId): void
    {
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('Approver is required.');
        }
        $this->transition(PurchaseOrderState::Submitted, PurchaseOrderState::Approved);
        $this->approvedBy = $actorId;
    }

    public function send(): void
    {
        $this->transition(PurchaseOrderState::Approved, PurchaseOrderState::Sent);
    }

    /** @param array<string,int> $receivedByLine */
    public function recordReceipt(array $receivedByLine): void
    {
        if (! in_array($this->state, [PurchaseOrderState::Sent, PurchaseOrderState::PartiallyReceived], true)) {
            throw new DomainException('Only sent purchase orders can receive goods.');
        }
        foreach ($receivedByLine as $lineId => $quantity) {
            $matched = false;
            foreach ($this->lines as $line) {
                if ($line->id === $lineId) {
                    $line->receive($quantity);
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                throw new DomainException("Unknown purchase order line {$lineId}.");
            }
        }
        $this->state = count(array_filter($this->lines, static fn (PurchaseOrderLine $line): bool => $line->isFullyReceived())) === count($this->lines)
            ? PurchaseOrderState::Received
            : PurchaseOrderState::PartiallyReceived;
    }

    public function markBilled(): void
    {
        if (! in_array($this->state, [PurchaseOrderState::PartiallyReceived, PurchaseOrderState::Received], true)) {
            throw new DomainException('A purchase order must have receipts before billing.');
        }
        $this->state = PurchaseOrderState::Billed;
    }

    private function transition(PurchaseOrderState $from, PurchaseOrderState $to): void
    {
        if ($this->state !== $from) {
            throw new DomainException("Purchase order cannot transition from {$this->state->value} to {$to->value}.");
        }
        $this->state = $to;
    }
}
