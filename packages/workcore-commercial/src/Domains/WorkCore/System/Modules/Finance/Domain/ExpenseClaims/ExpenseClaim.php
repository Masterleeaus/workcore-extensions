<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\ExpenseClaims;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class ExpenseClaim
{
    private ExpenseClaimState $state = ExpenseClaimState::Draft;
    private ?string $approvedBy = null;
    private ?string $reimbursementBatchId = null;

    /** @param list<string> $receiptIds */
    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $claimantId,
        public readonly int $amountMinor,
        public readonly string $currencyCode,
        public readonly string $categoryCode,
        public readonly DateTimeImmutable $incurredOn,
        public readonly array $receiptIds,
        public readonly ?string $workOrderId = null,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($claimantId) === '') {
            throw new InvalidArgumentException('Claim, company and claimant identity are required.');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Claim amount must be positive.');
        }
        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode))) {
            throw new InvalidArgumentException('Claim currency must be a three-letter code.');
        }
        if (trim($categoryCode) === '') {
            throw new InvalidArgumentException('Expense category is required.');
        }
    }

    public function state(): ExpenseClaimState
    {
        return $this->state;
    }

    public function submit(): void
    {
        if ($this->state !== ExpenseClaimState::Draft) {
            throw new DomainException('Only draft claims can be submitted.');
        }
        if ($this->receiptIds === []) {
            throw new DomainException('At least one receipt is required before submission.');
        }
        $this->state = ExpenseClaimState::Submitted;
    }

    public function approve(string $actorId): void
    {
        if ($this->state !== ExpenseClaimState::Submitted || trim($actorId) === '') {
            throw new DomainException('Only submitted claims can be approved by a named actor.');
        }
        $this->approvedBy = $actorId;
        $this->state = ExpenseClaimState::Approved;
    }

    public function reject(): void
    {
        if ($this->state !== ExpenseClaimState::Submitted) {
            throw new DomainException('Only submitted claims can be rejected.');
        }
        $this->state = ExpenseClaimState::Rejected;
    }

    public function markReimbursed(string $batchId): void
    {
        if ($this->state !== ExpenseClaimState::Approved || trim($batchId) === '') {
            throw new DomainException('Only approved claims can be reimbursed from a named batch.');
        }
        $this->reimbursementBatchId = $batchId;
        $this->state = ExpenseClaimState::Reimbursed;
    }
}
