<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger;

enum AccountRole: string
{
    case AccountsReceivable = 'accounts_receivable';
    case AccountsPayable = 'accounts_payable';
    case SalesRevenue = 'sales_revenue';
    case GstPayable = 'gst_payable';
    case GstReceivable = 'gst_receivable';
    case Bank = 'bank';
    case UndepositedFunds = 'undeposited_funds';
    case Expense = 'expense';
    case CustomerCredits = 'customer_credits';
    case RefundsPayable = 'refunds_payable';
}
