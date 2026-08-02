<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class IngestPaymentEvidenceInput implements ActionInput
{
    public function __construct(
        public string $sourceType,
        public int $amountMinor,
        public string $currencyCode,
        public string $referenceText,
        public ?string $payerHint,
        public string $rawPayloadHash,
        public DateTimeImmutable $observedAt,
        public ?string $sourceDeviceId,
        public string $paymentMethod = 'bank_transfer',
    ) {
        if (trim($sourceType) === '' || $amountMinor <= 0 || ! preg_match('/^[A-Fa-f0-9]{64}$/', $rawPayloadHash)) {
            throw new InvalidArgumentException('Valid payment evidence source, amount and SHA-256 payload hash are required.');
        }
        if (! in_array($paymentMethod, ['cash','payid','bank_transfer','paypal_card'], true)) {
            throw new InvalidArgumentException('Unsupported customer payment method.');
        }
        if (! preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode))) {
            throw new InvalidArgumentException('A three-letter currency code is required.');
        }
    }
    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'amount_minor' => $this->amountMinor,
            'currency_code' => strtoupper($this->currencyCode),
            'reference_text' => $this->referenceText,
            'payer_hint' => $this->payerHint,
            'raw_payload_hash' => strtolower($this->rawPayloadHash),
            'observed_at' => $this->observedAt->format(DATE_ATOM),
            'source_device_id' => $this->sourceDeviceId,
            'payment_method' => $this->paymentMethod,
        ];
    }
}
