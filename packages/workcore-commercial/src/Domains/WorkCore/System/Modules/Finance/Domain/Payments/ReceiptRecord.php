<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class ReceiptRecord
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $paymentId,
        public string $invoiceId,
        public string $receiptNumber,
        public Money $amount,
        public DateTimeImmutable $issuedAt,
        public string $state = 'queued',
    ) {}
}
