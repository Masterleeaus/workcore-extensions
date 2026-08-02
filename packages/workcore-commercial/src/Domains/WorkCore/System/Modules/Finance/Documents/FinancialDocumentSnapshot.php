<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Documents;

use App\Domains\WorkCore\System\Modules\Finance\Support\CanonicalJson;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FinancialDocumentSnapshot
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public string $companyId,
        public string $documentType,
        public string $documentId,
        public int $version,
        public array $payload,
        public string $payloadHash,
        public DateTimeImmutable $capturedAt,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function capture(
        string $companyId,
        string $documentType,
        string $documentId,
        int $version,
        array $payload,
        DateTimeImmutable $capturedAt,
    ): self {
        if (trim($companyId) === '' || trim($documentId) === '') {
            throw new InvalidArgumentException('Document snapshots require company and document IDs.');
        }

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $documentType) !== 1) {
            throw new InvalidArgumentException('Document snapshot type must be a lower-case machine identifier.');
        }

        if ($version < 1) {
            throw new InvalidArgumentException('Document snapshot version must be at least one.');
        }

        $canonical = CanonicalJson::encode($payload);

        return new self(
            $companyId,
            $documentType,
            $documentId,
            $version,
            $payload,
            hash('sha256', $canonical),
            $capturedAt,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'version' => $this->version,
            'payload' => $this->payload,
            'payload_hash' => $this->payloadHash,
            'captured_at' => $this->capturedAt->format(DATE_ATOM),
        ];
    }
}
