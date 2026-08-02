<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRequestRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;

final class InMemoryPaymentRequestRepository implements PaymentRequestRepository
{
    /** @var array<string,PaymentRequestRecord> */ public array $records = [];
    public function findOpenForInvoice(string $companyId, string $invoiceId): ?PaymentRequestRecord
    {
        foreach ($this->records as $record) if ($record->companyId === $companyId && $record->invoiceId === $invoiceId && $record->state === 'open') return $record;
        return null;
    }
    public function create(PaymentRequestRecord $request, ActionContext $context): PaymentRequestRecord { return $this->records[$request->id] = $request; }
}
