<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class QrArtifact
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $paymentRequestId,
        public string $documentId,
        public string $contentType,
        public string $payloadHash,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        foreach ([$id, $companyId, $paymentRequestId, $documentId, $contentType, $payloadHash] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('QR artifact identity values must not be blank.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'payment_request_id' => $this->paymentRequestId,
            'document_id' => $this->documentId,
            'content_type' => $this->contentType,
            'payload_hash' => $this->payloadHash,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
        ];
    }
}
