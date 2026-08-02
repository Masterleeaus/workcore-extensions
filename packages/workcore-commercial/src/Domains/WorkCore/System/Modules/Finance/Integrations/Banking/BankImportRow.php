<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class BankImportRow
{
    public function __construct(
        public int $sourceRow,
        public DateTimeImmutable $transactionDate,
        public string $description,
        public string $referenceText,
        public ?string $payerPayeeHint,
        public Money $amount,
        public string $transactionHash,
    ) {
        if ($sourceRow < 2 || ! preg_match('/^[a-f0-9]{64}$/', $transactionHash)) {
            throw new InvalidArgumentException('Bank import row requires a source row and SHA-256 transaction hash.');
        }
    }
}
