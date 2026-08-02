<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use InvalidArgumentException;

final readonly class JournalLine
{
    public function __construct(
        public AccountRole $accountRole,
        public LedgerSide $side,
        public Money $amount,
        public ?string $memo = null,
        public array $dimensions = [],
    ) {
        if ($amount->minorAmount <= 0) {
            throw new InvalidArgumentException('Journal line amounts must be positive.');
        }
    }

    public function toArray(): array
    {
        return [
            'account_role' => $this->accountRole->value,
            'side' => $this->side->value,
            'amount' => $this->amount->toArray(),
            'memo' => $this->memo,
            'dimensions' => $this->dimensions,
        ];
    }
}
