<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceDraft;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceRecord;
use DateTimeImmutable;

interface InvoiceRepository
{
    public function findById(string $companyId, string $invoiceId): ?InvoiceRecord;

    public function findBySourceKey(string $companyId, string $sourceKey): ?InvoiceRecord;

    public function create(InvoiceDraft $draft, ActionContext $context): InvoiceRecord;

    public function issue(
        string $companyId,
        string $invoiceId,
        string $snapshotHash,
        DateTimeImmutable $issuedAt,
        ActionContext $context,
    ): InvoiceRecord;

    /** @param array<string,mixed> $payload */
    public function appendEvent(
        string $companyId,
        string $invoiceId,
        string $eventType,
        array $payload,
        DateTimeImmutable $occurredAt,
        ActionContext $context,
    ): void;
}
