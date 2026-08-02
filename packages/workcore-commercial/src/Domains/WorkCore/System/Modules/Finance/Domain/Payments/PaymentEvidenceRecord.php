<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final readonly class PaymentEvidenceRecord
{
    public function __construct(
        public string $id,
        public string $companyId,
        public string $sourceType,
        public Money $amount,
        public string $referenceText,
        public ?string $payerHint,
        public string $rawPayloadHash,
        public DateTimeImmutable $observedAt,
        public ?string $sourceDeviceId,
        public string $duplicateStatus = 'unique',
        public string $verificationStatus = 'unverified',
        public string $paymentMethod = 'bank_transfer',
    ) {}
}
