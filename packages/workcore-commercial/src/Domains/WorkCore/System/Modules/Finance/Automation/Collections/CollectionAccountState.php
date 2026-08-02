<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Collections;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CollectionAccountState
{
    public function __construct(
        public string $invoiceId,
        public int $balanceMinor,
        public string $currencyCode,
        public bool $paid = false,
        public bool $disputeOpen = false,
        public bool $paymentPlanActive = false,
        public bool $paymentClaimPending = false,
        public bool $paused = false,
        public bool $voided = false,
        public bool $credited = false,
        public ?DateTimeImmutable $promiseDueAt = null,
    ) {
        if (trim($invoiceId) === '' || ! preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode))) {
            throw new InvalidArgumentException('Collection state requires an invoice and currency.');
        }
        if ($balanceMinor < 0) {
            throw new InvalidArgumentException('Invoice balance cannot be negative.');
        }
    }
}
