<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class ReceivableBalance
{
    public function __construct(public string $invoiceId, public DateTimeImmutable $dueDate, public Money $amountDue) {}
}
