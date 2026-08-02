<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Budgeting;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class BudgetEnvelope
{
    private BudgetState $state = BudgetState::Draft;
    private int $committedMinor = 0;
    private int $actualMinor = 0;
    private ?string $approvedBy = null;

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $name,
        public readonly string $currencyCode,
        public readonly int $allocatedMinor,
        public readonly DateTimeImmutable $startsOn,
        public readonly DateTimeImmutable $endsOn,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($name) === '') {
            throw new InvalidArgumentException('Budget identity, company and name are required.');
        }
        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode))) {
            throw new InvalidArgumentException('Budget currency must be a three-letter code.');
        }
        if ($allocatedMinor < 0) {
            throw new InvalidArgumentException('Budget allocation cannot be negative.');
        }
        if ($endsOn < $startsOn) {
            throw new InvalidArgumentException('Budget end date must not precede start date.');
        }
    }

    public function state(): BudgetState
    {
        return $this->state;
    }

    public function submit(): void
    {
        $this->transition(BudgetState::Draft, BudgetState::Submitted);
    }

    public function approve(string $actorId): void
    {
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('Approver is required.');
        }
        $this->transition(BudgetState::Submitted, BudgetState::Approved);
        $this->approvedBy = $actorId;
    }

    public function reject(): void
    {
        $this->transition(BudgetState::Submitted, BudgetState::Rejected);
    }

    public function activate(): void
    {
        $this->transition(BudgetState::Approved, BudgetState::Active);
    }

    public function close(): void
    {
        $this->transition(BudgetState::Active, BudgetState::Closed);
    }

    public function commit(int $amountMinor): void
    {
        $this->assertActive();
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Committed amount must be positive.');
        }
        if ($this->committedMinor + $this->actualMinor + $amountMinor > $this->allocatedMinor) {
            throw new DomainException('Commitment exceeds available budget.');
        }
        $this->committedMinor += $amountMinor;
    }

    public function postActual(int $amountMinor): void
    {
        $this->assertActive();
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Actual amount must be positive.');
        }
        if ($amountMinor > $this->committedMinor) {
            throw new DomainException('Actual amount cannot exceed committed amount without an approved adjustment.');
        }
        $this->committedMinor -= $amountMinor;
        $this->actualMinor += $amountMinor;
    }

    public function committedMinor(): int
    {
        return $this->committedMinor;
    }

    public function actualMinor(): int
    {
        return $this->actualMinor;
    }

    public function remainingMinor(): int
    {
        return $this->allocatedMinor - $this->committedMinor - $this->actualMinor;
    }

    public function varianceMinor(): int
    {
        return $this->allocatedMinor - $this->actualMinor - $this->remainingMinor();
    }

    private function transition(BudgetState $from, BudgetState $to): void
    {
        if ($this->state !== $from) {
            throw new DomainException("Budget cannot transition from {$this->state->value} to {$to->value}.");
        }
        $this->state = $to;
    }

    private function assertActive(): void
    {
        if ($this->state !== BudgetState::Active) {
            throw new DomainException('Budget must be active.');
        }
    }
}
