<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use InvalidArgumentException;

final readonly class ReconciliationSession
{
    public function __construct(public string $id,public string $companyId,public string $bankAccountId,public int $statementClosingBalanceMinor,public int $reconciledClosingBalanceMinor,public string $currencyCode,public int $unresolvedLineCount,public string $state)
    {if($unresolvedLineCount<0)throw new InvalidArgumentException('Unresolved line count cannot be negative.');}
    public function balance():ReconciliationBalance{return new ReconciliationBalance(Money::ofMinor($this->statementClosingBalanceMinor,$this->currencyCode),Money::ofMinor($this->reconciledClosingBalanceMinor,$this->currencyCode));}
}
