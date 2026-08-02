<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Payroll\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class PayrollRun
{
    private PayrollRunState $state = PayrollRunState::Draft;
    /** @var array<string,list<PayLine>> */
    private array $workerPay = [];
    private int $grossMinor = 0;
    private int $netMinor = 0;
    private ?string $approvedBy = null;
    private ?string $journalExportId = null;

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $currencyCode,
        public readonly DateTimeImmutable $periodStartsOn,
        public readonly DateTimeImmutable $periodEndsOn,
    ) {
        if (trim($id) === '' || trim($companyId) === '') throw new InvalidArgumentException('Payroll run and company identity are required.');
        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode))) throw new InvalidArgumentException('Payroll currency must be a three-letter code.');
        if ($periodEndsOn < $periodStartsOn) throw new InvalidArgumentException('Payroll period end cannot precede its start.');
    }
    public function state(): PayrollRunState { return $this->state; }
    public function grossMinor(): int { return $this->grossMinor; }
    public function netMinor(): int { return $this->netMinor; }
    public function journalExportId(): ?string { return $this->journalExportId; }
    /** @param list<PayLine> $lines */
    public function addWorkerPay(string $workerId, array $lines): void
    {
        if ($this->state !== PayrollRunState::Draft) throw new DomainException('Worker pay can only be changed on draft payroll runs.');
        if (trim($workerId) === '' || $lines === []) throw new InvalidArgumentException('Worker identity and pay lines are required.');
        $this->workerPay[$workerId] = $lines;
    }
    public function calculate(): void
    {
        if ($this->state !== PayrollRunState::Draft || $this->workerPay === []) throw new DomainException('A populated draft payroll run is required for calculation.');
        $gross = 0; $net = 0;
        foreach ($this->workerPay as $lines) {
            foreach ($lines as $line) {
                $net += $line->amountMinor;
                if ($line->amountMinor > 0) $gross += $line->amountMinor;
            }
        }
        $this->grossMinor = $gross;
        $this->netMinor = $net;
        $this->state = PayrollRunState::Calculated;
    }
    public function submit(): void { $this->move(PayrollRunState::Calculated, PayrollRunState::Submitted); }
    public function approve(string $actorId): void
    {
        if (trim($actorId) === '') throw new InvalidArgumentException('A named payroll approver is required.');
        $this->move(PayrollRunState::Submitted, PayrollRunState::Approved);
        $this->approvedBy = $actorId;
    }
    public function finalize(string $journalExportId): void
    {
        if (trim($journalExportId) === '') throw new InvalidArgumentException('Titan Money journal export identity is required.');
        $this->move(PayrollRunState::Approved, PayrollRunState::Finalized);
        $this->journalExportId = $journalExportId;
    }
    private function move(PayrollRunState $from, PayrollRunState $to): void
    {
        if ($this->state !== $from) throw new DomainException("Payroll run must be {$from->value} before moving to {$to->value}.");
        $this->state = $to;
    }
}
