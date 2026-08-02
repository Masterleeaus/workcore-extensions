<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\InvoiceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceDraft;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceState;
use DateTimeImmutable;
use RuntimeException;

final class InMemoryInvoiceRepository implements InvoiceRepository
{
    /** @var array<string,InvoiceRecord> */ public array $records = [];
    /** @var list<array<string,mixed>> */ public array $events = [];

    public function findById(string $companyId, string $invoiceId): ?InvoiceRecord
    {
        $record = $this->records[$invoiceId] ?? null;
        return $record?->companyId() === $companyId ? $record : null;
    }

    public function findBySourceKey(string $companyId, string $sourceKey): ?InvoiceRecord
    {
        foreach ($this->records as $record) {
            if ($record->companyId() === $companyId && $record->draft->sourceKey === $sourceKey) return $record;
        }
        return null;
    }

    public function create(InvoiceDraft $draft, ActionContext $context): InvoiceRecord
    {
        if ($this->findBySourceKey($draft->companyId, $draft->sourceKey)) throw new RuntimeException('Invoice source already exists.');
        return $this->records[$draft->id] = new InvoiceRecord($draft);
    }

    public function issue(string $companyId, string $invoiceId, string $snapshotHash, DateTimeImmutable $issuedAt, ActionContext $context): InvoiceRecord
    {
        $record = $this->findById($companyId, $invoiceId) ?? throw new RuntimeException('Invoice not found.');
        return $this->records[$invoiceId] = new InvoiceRecord($record->draft, InvoiceState::Issued, $issuedAt, $snapshotHash);
    }

    public function appendEvent(string $companyId, string $invoiceId, string $eventType, array $payload, DateTimeImmutable $occurredAt, ActionContext $context): void
    {
        $this->events[] = compact('companyId','invoiceId','eventType','payload','occurredAt','context');
    }
}
