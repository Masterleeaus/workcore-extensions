<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\EInvoicing;

use DomainException;
use InvalidArgumentException;

final class EInvoiceTransmission
{
    private EInvoiceState $state = EInvoiceState::Draft;
    private ?string $providerMessageId = null;
    private ?string $rejectionCode = null;

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $invoiceId,
        public readonly EInvoiceFormat $format,
        public readonly string $receiverEndpoint,
        public readonly string $documentHash,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($invoiceId) === '' || trim($receiverEndpoint) === '') {
            throw new InvalidArgumentException('Transmission, company, invoice and receiver endpoint are required.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/i', $documentHash)) {
            throw new InvalidArgumentException('Electronic invoice document hash must be SHA-256.');
        }
    }

    public function state(): EInvoiceState
    {
        return $this->state;
    }

    public function validate(): void
    {
        $this->transition(EInvoiceState::Draft, EInvoiceState::Validated);
    }

    public function queue(): void
    {
        $this->transition(EInvoiceState::Validated, EInvoiceState::Queued);
    }

    public function markSent(string $providerMessageId): void
    {
        if (trim($providerMessageId) === '') {
            throw new InvalidArgumentException('Provider message identifier is required.');
        }
        $this->transition(EInvoiceState::Queued, EInvoiceState::Sent);
        $this->providerMessageId = $providerMessageId;
    }

    public function markDelivered(): void
    {
        $this->transition(EInvoiceState::Sent, EInvoiceState::Delivered);
    }

    public function markAccepted(): void
    {
        $this->transition(EInvoiceState::Delivered, EInvoiceState::Accepted);
    }

    public function reject(string $code): void
    {
        if (! in_array($this->state, [EInvoiceState::Sent, EInvoiceState::Delivered], true) || trim($code) === '') {
            throw new DomainException('Only sent or delivered transmissions can be rejected with a code.');
        }
        $this->rejectionCode = $code;
        $this->state = EInvoiceState::Rejected;
    }

    private function transition(EInvoiceState $from, EInvoiceState $to): void
    {
        if ($this->state !== $from) {
            throw new DomainException("E-invoice cannot transition from {$this->state->value} to {$to->value}.");
        }
        $this->state = $to;
    }
}
