<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\WorkforceLoans;

use DomainException;
use InvalidArgumentException;

final class WorkforceLoan
{
    private WorkforceLoanState $state = WorkforceLoanState::Draft;
    private int $outstandingMinor;
    private ?string $approvedBy = null;
    private ?string $disbursementPaymentId = null;
    /** @var array<string,int> */
    private array $repayments = [];

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $workerId,
        public readonly int $principalMinor,
        public readonly string $currencyCode,
        public readonly int $scheduledRepaymentMinor,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '') {
            throw new InvalidArgumentException('Loan, company and worker identity are required.');
        }
        if ($principalMinor <= 0 || $scheduledRepaymentMinor <= 0 || $scheduledRepaymentMinor > $principalMinor) {
            throw new InvalidArgumentException('Loan principal and scheduled repayment must be positive and valid.');
        }
        $this->outstandingMinor = $principalMinor;
    }

    public function state(): WorkforceLoanState
    {
        return $this->state;
    }

    public function outstandingMinor(): int
    {
        return $this->outstandingMinor;
    }

    public function submit(): void
    {
        $this->transition(WorkforceLoanState::Draft, WorkforceLoanState::Submitted);
    }

    public function approve(string $actorId): void
    {
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('Approver is required.');
        }
        $this->transition(WorkforceLoanState::Submitted, WorkforceLoanState::Approved);
        $this->approvedBy = $actorId;
    }

    public function decline(): void
    {
        $this->transition(WorkforceLoanState::Submitted, WorkforceLoanState::Declined);
    }

    public function disburse(string $paymentId): void
    {
        if (trim($paymentId) === '') {
            throw new InvalidArgumentException('Disbursement payment identifier is required.');
        }
        $this->transition(WorkforceLoanState::Approved, WorkforceLoanState::Active);
        $this->disbursementPaymentId = $paymentId;
    }

    public function recordRepayment(int $amountMinor, string $reference): void
    {
        if ($this->state !== WorkforceLoanState::Active) {
            throw new DomainException('Only active loans accept repayments.');
        }
        if ($amountMinor <= 0 || $amountMinor > $this->outstandingMinor || trim($reference) === '') {
            throw new InvalidArgumentException('Repayment must be positive, referenced and not exceed outstanding balance.');
        }
        if (isset($this->repayments[$reference])) {
            throw new DomainException('Duplicate loan repayment reference.');
        }
        $this->repayments[$reference] = $amountMinor;
        $this->outstandingMinor -= $amountMinor;
        if ($this->outstandingMinor === 0) {
            $this->state = WorkforceLoanState::Repaid;
        }
    }

    private function transition(WorkforceLoanState $from, WorkforceLoanState $to): void
    {
        if ($this->state !== $from) {
            throw new DomainException("Loan cannot transition from {$this->state->value} to {$to->value}.");
        }
        $this->state = $to;
    }
}
