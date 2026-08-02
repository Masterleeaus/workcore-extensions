<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Device;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\IngestPaymentEvidenceInput;
use DateTimeImmutable;
use InvalidArgumentException;

final class DevicePaymentNotificationAdapter
{
    /** @param array<string,mixed> $payload */
    public function adapt(array $payload): IngestPaymentEvidenceInput
    {
        foreach (['device_id','amount_minor','currency_code','observed_at'] as $key) if (! isset($payload[$key]) || $payload[$key] === '') throw new InvalidArgumentException("Device payment notification requires {$key}.");
        $raw = (string) ($payload['raw_notification'] ?? json_encode($payload, JSON_THROW_ON_ERROR));
        return new IngestPaymentEvidenceInput(
            sourceType: 'device_notification',
            amountMinor: (int) $payload['amount_minor'],
            currencyCode: strtoupper((string) $payload['currency_code']),
            referenceText: trim((string) ($payload['reference_text'] ?? '')),
            payerHint: isset($payload['payer_hint']) && trim((string) $payload['payer_hint']) !== '' ? trim((string) $payload['payer_hint']) : null,
            rawPayloadHash: hash('sha256', $raw),
            observedAt: new DateTimeImmutable((string) $payload['observed_at']),
            sourceDeviceId: (string) $payload['device_id'],
            paymentMethod: (string) ($payload['payment_method'] ?? 'payid'),
        );
    }
}
