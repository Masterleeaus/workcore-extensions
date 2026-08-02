<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final class ReceivableAccount
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $invoiceNumber,
        public readonly string $companyId,
        public readonly string $customerId,
        public Money $balance,
        public readonly string $paymentReference,
        public readonly ?string $payerHint,
        public readonly DateTimeImmutable $issuedAt,
        public string $state = 'issued',
    ) {}
}
