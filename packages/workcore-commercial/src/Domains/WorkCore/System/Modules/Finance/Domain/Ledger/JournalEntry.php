<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final readonly class JournalEntry
{
    /** @param list<JournalLine> $lines */
    public function __construct(
        public string $companyId,
        public string $reference,
        public DateTimeImmutable $accountingDate,
        public string $sourceType,
        public string $sourceId,
        public array $lines,
        public ?string $description = null,
    ) {
        if (trim($companyId) === '' || trim($reference) === '' || trim($sourceType) === '' || trim($sourceId) === '') {
            throw new InvalidArgumentException('Journal identity values must not be blank.');
        }
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry requires at least two lines.');
        }
        foreach ($lines as $line) {
            if (! $line instanceof JournalLine) {
                throw new InvalidArgumentException('Every journal entry line must be a JournalLine.');
            }
        }
        $currency = $lines[0]->amount->currency;
        foreach ($lines as $line) {
            if (! $currency->equals($line->amount->currency)) {
                throw new DomainException('Journal entries cannot mix currencies.');
            }
        }
        if (! $this->debitTotal()->equals($this->creditTotal())) {
            throw new DomainException('Journal entry debits and credits must balance.');
        }
    }

    public function debitTotal(): Money
    {
        return $this->totalFor(LedgerSide::Debit);
    }

    public function creditTotal(): Money
    {
        return $this->totalFor(LedgerSide::Credit);
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'reference' => $this->reference,
            'accounting_date' => $this->accountingDate->format('Y-m-d'),
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'description' => $this->description,
            'lines' => array_map(static fn (JournalLine $line): array => $line->toArray(), $this->lines),
            'debit_total' => $this->debitTotal()->toArray(),
            'credit_total' => $this->creditTotal()->toArray(),
        ];
    }

    private function totalFor(LedgerSide $side): Money
    {
        $currency = $this->lines[0]->amount->currency;
        $total = Money::zero($currency);
        foreach ($this->lines as $line) {
            if ($line->side === $side) {
                $total = $total->add($line->amount);
            }
        }
        return $total;
    }
}
