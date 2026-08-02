<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\AccountRole;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalEntry;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalLine;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\LedgerSide;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;

final class PaymentReceivedPostingTemplate
{
    public function build(string $companyId, string $paymentId, DateTimeImmutable $accountingDate, Money $amount, bool $settled = false): JournalEntry
    {
        $debitRole = $settled ? AccountRole::Bank : AccountRole::UndepositedFunds;
        return new JournalEntry(
            $companyId,
            'PAY-'.$paymentId,
            $accountingDate,
            'payment',
            $paymentId,
            [
                new JournalLine($debitRole, LedgerSide::Debit, $amount, 'Payment received'),
                new JournalLine(AccountRole::AccountsReceivable, LedgerSide::Credit, $amount, 'Receivable cleared'),
            ],
            'Customer payment received',
        );
    }
}
