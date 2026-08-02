<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\AccountRole;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalEntry;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalLine;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\LedgerSide;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final class PaymentConfirmedPostingTemplate
{
    public function build(string $companyId,string $paymentId,DateTimeImmutable $accountingDate,Money $amount,int $allocatedMinor,bool $settled=false): JournalEntry
    {
        if($allocatedMinor<0||$allocatedMinor>$amount->minorAmount) throw new InvalidArgumentException('Allocated amount must be within payment amount.');
        $lines=[new JournalLine($settled?AccountRole::Bank:AccountRole::UndepositedFunds,LedgerSide::Debit,$amount,'Payment confirmed')];
        if($allocatedMinor>0)$lines[]=new JournalLine(AccountRole::AccountsReceivable,LedgerSide::Credit,Money::ofMinor($allocatedMinor,$amount->currency->value),'Receivable cleared');
        $credit=$amount->minorAmount-$allocatedMinor;
        if($credit>0)$lines[]=new JournalLine(AccountRole::CustomerCredits,LedgerSide::Credit,Money::ofMinor($credit,$amount->currency->value),'Unallocated customer credit');
        return new JournalEntry($companyId,'PAY-'.$paymentId,$accountingDate,'payment',$paymentId,$lines,'Customer payment confirmed');
    }
}
