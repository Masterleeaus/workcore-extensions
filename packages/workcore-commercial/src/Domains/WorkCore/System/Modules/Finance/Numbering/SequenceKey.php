<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Numbering;

enum SequenceKey: string
{
    case Estimate = 'estimate';
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Receipt = 'receipt';
    case Expense = 'expense';
    case SupplierBill = 'supplier_bill';
    case PurchaseOrder = 'purchase_order';
    case ReimbursementBatch = 'reimbursement_batch';
    case PosSale = 'pos_sale';
    case SalesOrder = 'sales_order';
    case Journal = 'journal';

    /** @return list<string> */
    public static function values(): array
    {
        return array_values(array_map(
            static fn (self $key): string => $key->value,
            self::cases(),
        ));
    }
}
