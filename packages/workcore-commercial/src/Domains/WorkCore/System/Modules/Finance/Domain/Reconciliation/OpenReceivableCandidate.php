<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class OpenReceivableCandidate
{
    public function __construct(public string $invoiceId, public Money $amountDue, public string $paymentReference, public ?string $payerHint, public DateTimeImmutable $issuedAt) {}
}
