<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BankDepositEvidence
{
    public function __construct(public Money $amount, public string $referenceText, public ?string $payerHint, public DateTimeImmutable $observedAt, public string $rawPayloadHash)
    {
        if ($amount->minorAmount <= 0 || trim($rawPayloadHash)==='') throw new InvalidArgumentException('Valid positive evidence and payload hash are required.');
    }
}
