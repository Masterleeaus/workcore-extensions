<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation;

enum FinancialActionType: string
{
    case CreateInvoice = 'create_invoice';
    case IssueInvoice = 'issue_invoice';
    case SendInvoice = 'send_invoice';
    case SendPaymentQr = 'send_payment_qr';
    case SendReminder = 'send_reminder';
    case ProposePaymentMatch = 'propose_payment_match';
    case ConfirmPayment = 'confirm_payment';
    case AllocatePayment = 'allocate_payment';
    case SendReceipt = 'send_receipt';
    case ApproveExpense = 'approve_expense';
    case Refund = 'refund';
    case WriteOff = 'write_off';
    case ChangePaymentCredentials = 'change_payment_credentials';
    case ClosePeriod = 'close_period';
    case CreateBudget = 'create_budget';
    case ApproveBudget = 'approve_budget';
    case SubmitExpenseClaim = 'submit_expense_claim';
    case ApproveExpenseClaim = 'approve_expense_claim';
    case CreatePurchaseOrder = 'create_purchase_order';
    case ApprovePurchaseOrder = 'approve_purchase_order';
    case ReceivePurchaseOrder = 'receive_purchase_order';
    case SendEInvoice = 'send_einvoice';
    case ApproveWorkforceLoan = 'approve_workforce_loan';
    case DisburseWorkforceLoan = 'disburse_workforce_loan';
    case CompletePosSale = 'complete_pos_sale';
    case VoidPosSale = 'void_pos_sale';
    case FulfilSalesOrder = 'fulfil_sales_order';
}
