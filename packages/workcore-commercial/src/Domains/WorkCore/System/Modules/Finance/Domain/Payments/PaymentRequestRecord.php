<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PaymentRequestRecord
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $customerId,
        public string $invoiceId,
        public Money $amount,
        public string $reference,
        public PaymentMethodKey $preferredMethod,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt = null,
        public string $state = 'open',
    ) {
        foreach ([$id, $companyId, $customerId, $invoiceId, $reference] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Payment request identity values must not be blank.');
            }
        }
        if ($amount->minorAmount <= 0) {
            throw new InvalidArgumentException('Payment request amount must be positive.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'customer_id' => $this->customerId,
            'invoice_id' => $this->invoiceId,
            'amount' => $this->amount->toArray(),
            'reference' => $this->reference,
            'preferred_method' => $this->preferredMethod->value,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'state' => $this->state,
        ];
    }
}
