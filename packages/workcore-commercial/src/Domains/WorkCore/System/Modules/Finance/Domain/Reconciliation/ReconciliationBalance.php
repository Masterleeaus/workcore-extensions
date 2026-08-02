<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class ReconciliationBalance
{
    public function __construct(public Money $statementClosingBalance, public Money $reconciledClosingBalance) {}
    public function difference(): Money { return $this->statementClosingBalance->subtract($this->reconciledClosingBalance); }
    public function canClose(): bool { return $this->difference()->isZero(); }
    public function toArray(): array { return ['statement_closing_balance'=>$this->statementClosingBalance->toArray(),'reconciled_closing_balance'=>$this->reconciledClosingBalance->toArray(),'difference'=>$this->difference()->toArray(),'can_close'=>$this->canClose()]; }
}
