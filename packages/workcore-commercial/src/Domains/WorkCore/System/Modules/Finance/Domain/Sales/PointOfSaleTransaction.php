<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Sales;

use DomainException;
use InvalidArgumentException;

final class PointOfSaleTransaction
{
    private SaleState $state = SaleState::Draft;
    /** @var array<string,array{method:string,amount_minor:int}> */
    private array $payments = [];

    /** @param list<SaleLine> $lines */
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $currencyCode,
        public readonly array $lines,
        public readonly ?string $customerId = null,
        public readonly ?string $workOrderId = null,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || $lines === []) {
            throw new InvalidArgumentException('Sale identity, company and at least one line are required.');
        }
        foreach ($lines as $line) {
            if (! $line instanceof SaleLine) {
                throw new InvalidArgumentException('Every sale line must be a SaleLine.');
            }
        }
    }

    public function state(): SaleState
    {
        return $this->state;
    }

    public function totalMinor(): int
    {
        return array_sum(array_map(static fn (SaleLine $line): int => $line->totalMinor(), $this->lines));
    }

    public function open(): void
    {
        if ($this->state !== SaleState::Draft) {
            throw new DomainException('Only draft sales can be opened.');
        }
        $this->state = SaleState::Open;
    }

    public function addPayment(string $method, int $amountMinor, string $reference): void
    {
        if ($this->state !== SaleState::Open) {
            throw new DomainException('Payments require an open sale.');
        }
        if (trim($method) === '' || trim($reference) === '' || $amountMinor <= 0) {
            throw new InvalidArgumentException('Payment method, reference and positive amount are required.');
        }
        if (isset($this->payments[$reference])) {
            throw new DomainException('Duplicate sale payment reference.');
        }
        $this->payments[$reference] = ['method' => $method, 'amount_minor' => $amountMinor];
    }

    public function paidMinor(): int
    {
        return array_sum(array_column($this->payments, 'amount_minor'));
    }

    public function complete(): int
    {
        if ($this->state !== SaleState::Open) {
            throw new DomainException('Only open sales can be completed.');
        }
        if ($this->paidMinor() < $this->totalMinor()) {
            throw new DomainException('Sale is not fully settled.');
        }
        $this->state = SaleState::Completed;
        return $this->paidMinor() - $this->totalMinor();
    }

    public function void(): void
    {
        if (! in_array($this->state, [SaleState::Draft, SaleState::Open], true)) {
            throw new DomainException('Only uncompleted sales can be voided.');
        }
        $this->state = SaleState::Voided;
    }
}
