<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class PaymentRecord
{
    public function __construct(
        public string $id,
        public string $companyId,
        public ?string $customerId,
        public string $method,
        public string $state,
        public Money $amount,
        public int $allocatedMinor,
        public int $unallocatedMinor,
        public ?string $externalReference,
        public DateTimeImmutable $confirmedAt,
        public string $evidenceId,
    ) {}
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'amount_minor' => $this->amount->minorAmount,
            'allocated_minor' => $this->allocatedMinor,
            'unallocated_minor' => $this->unallocatedMinor,
            'currency_code' => $this->amount->currency->value,
            'confirmed_at' => $this->confirmedAt->format(DATE_ATOM),
        ];
    }
}
