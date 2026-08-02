<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Sales;

use DomainException;
use InvalidArgumentException;

final class SalesOrder
{
    private SalesOrderState $state = SalesOrderState::Draft;

    /** @param list<SaleLine> $lines */
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $requesterId,
        public readonly string $currencyCode,
        public readonly array $lines,
        public readonly ?string $workOrderId = null,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($requesterId) === '' || $lines === []) {
            throw new InvalidArgumentException('Sales order identity, company, requester and lines are required.');
        }
    }

    public function state(): SalesOrderState
    {
        return $this->state;
    }

    public function totalMinor(): int
    {
        return array_sum(array_map(static fn (SaleLine $line): int => $line->totalMinor(), $this->lines));
    }

    public function submit(): void { $this->transition(SalesOrderState::Draft, SalesOrderState::Submitted); }
    public function approve(): void { $this->transition(SalesOrderState::Submitted, SalesOrderState::Approved); }
    public function fulfil(): void { $this->transition(SalesOrderState::Approved, SalesOrderState::Fulfilled); }
    public function markInvoiced(): void { $this->transition(SalesOrderState::Fulfilled, SalesOrderState::Invoiced); }

    private function transition(SalesOrderState $from, SalesOrderState $to): void
    {
        if ($this->state !== $from) {
            throw new DomainException("Sales order cannot transition from {$this->state->value} to {$to->value}.");
        }
        $this->state = $to;
    }
}
