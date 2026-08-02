<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

final class InvoiceLifecycle
{
    public function canTransition(InvoiceState $from, InvoiceState $to): bool
    {
        return in_array($to, match($from) {
            InvoiceState::Draft => [InvoiceState::Approved, InvoiceState::Voided],
            InvoiceState::Approved => [InvoiceState::Issued, InvoiceState::Draft, InvoiceState::Voided],
            InvoiceState::Issued => [InvoiceState::Delivered, InvoiceState::Viewed, InvoiceState::PartiallyPaid, InvoiceState::Paid, InvoiceState::Overdue, InvoiceState::Credited, InvoiceState::Voided],
            InvoiceState::Delivered => [InvoiceState::Viewed, InvoiceState::PartiallyPaid, InvoiceState::Paid, InvoiceState::Overdue, InvoiceState::Credited],
            InvoiceState::Viewed => [InvoiceState::PartiallyPaid, InvoiceState::Paid, InvoiceState::Overdue, InvoiceState::Credited],
            InvoiceState::PartiallyPaid => [InvoiceState::Paid, InvoiceState::Overdue, InvoiceState::Credited],
            InvoiceState::Overdue => [InvoiceState::PartiallyPaid, InvoiceState::Paid, InvoiceState::Credited],
            InvoiceState::Paid => [InvoiceState::Credited],
            InvoiceState::Voided, InvoiceState::Credited => [],
        }, true);
    }
}
