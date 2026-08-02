<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\AccountRole;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalEntry;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalLine;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\LedgerSide;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;

final class InvoiceIssuedPostingTemplate
{
    public function build(string $companyId, string $invoiceId, DateTimeImmutable $accountingDate, Money $subtotal, Money $gst): JournalEntry
    {
        if ($subtotal->isNegative() || $gst->isNegative()) {
            throw new DomainException('Invoice posting amounts cannot be negative.');
        }
        $total = $subtotal->add($gst);
        $lines = [
            new JournalLine(AccountRole::AccountsReceivable, LedgerSide::Debit, $total, 'Invoice receivable'),
            new JournalLine(AccountRole::SalesRevenue, LedgerSide::Credit, $subtotal, 'Invoice revenue'),
        ];
        if (! $gst->isZero()) {
            $lines[] = new JournalLine(AccountRole::GstPayable, LedgerSide::Credit, $gst, 'GST collected');
        }
        return new JournalEntry($companyId, 'INV-'.$invoiceId, $accountingDate, 'invoice', $invoiceId, $lines, 'Invoice issued');
    }
}
