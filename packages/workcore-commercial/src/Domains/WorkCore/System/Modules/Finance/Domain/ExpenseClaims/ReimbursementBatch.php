<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\ExpenseClaims;

use DomainException;
use InvalidArgumentException;

final class ReimbursementBatch
{
    /** @var list<ExpenseClaim> */
    private array $claims = [];
    private bool $paid = false;

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $currencyCode,
    ) {
        if (trim($id) === '' || trim($companyId) === '') {
            throw new InvalidArgumentException('Batch and company identity are required.');
        }
    }

    public function add(ExpenseClaim $claim): void
    {
        if ($this->paid) {
            throw new DomainException('Paid reimbursement batches are immutable.');
        }
        if ($claim->companyId !== $this->companyId || strtoupper($claim->currencyCode) !== strtoupper($this->currencyCode)) {
            throw new DomainException('Claim must match batch company and currency.');
        }
        if ($claim->state() !== ExpenseClaimState::Approved) {
            throw new DomainException('Only approved claims can enter a reimbursement batch.');
        }
        $this->claims[] = $claim;
    }

    public function totalMinor(): int
    {
        return array_sum(array_map(static fn (ExpenseClaim $claim): int => $claim->amountMinor, $this->claims));
    }

    public function markPaid(): void
    {
        if ($this->claims === []) {
            throw new DomainException('Cannot pay an empty reimbursement batch.');
        }
        foreach ($this->claims as $claim) {
            $claim->markReimbursed($this->id);
        }
        $this->paid = true;
    }
}
