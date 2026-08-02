<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class InvoiceRecord
{
    public function __construct(
        public InvoiceDraft $draft,
        public InvoiceState $state = InvoiceState::Draft,
        public ?DateTimeImmutable $issuedAt = null,
        public ?string $issuedSnapshotHash = null,
    ) {
        if ($state === InvoiceState::Issued && ($issuedAt === null || $issuedSnapshotHash === null)) {
            throw new InvalidArgumentException('Issued invoices require issue evidence.');
        }
    }

    public function id(): string { return $this->draft->id; }
    public function companyId(): string { return $this->draft->companyId; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'invoice' => $this->draft->snapshotPayload(),
            'state' => $this->state->value,
            'issued_at' => $this->issuedAt?->format(DATE_ATOM),
            'issued_snapshot_hash' => $this->issuedSnapshotHash,
        ];
    }
}
