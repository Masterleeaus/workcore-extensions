<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Authorization;

enum TitanMoneyPermission: string
{
    case View = 'money.view';
    case SettingsView = 'money.settings.view';
    case SettingsManage = 'money.settings.manage';
    case SequencesManage = 'money.sequences.manage';
    case AuditView = 'money.audit.view';
    case EstimatesDraft = 'money.estimates.draft';
    case EstimatesManage = 'money.estimates.manage';
    case QuotesDraft = 'money.quotes.draft';
    case QuotesSend = 'money.quotes.send';
    case QuotesAccept = 'money.quotes.accept';
    case InvoicesView = 'money.invoices.view';
    case InvoicesDraft = 'money.invoices.draft';
    case InvoicesApprove = 'money.invoices.approve';
    case InvoicesIssue = 'money.invoices.issue';
    case PaymentsView = 'money.payments.view';
    case PaymentsReport = 'money.payments.report';
    case PaymentsConfirm = 'money.payments.confirm';
    case PaymentsAllocate = 'money.payments.allocate';
    case PaymentsRefund = 'money.payments.refund';
    case ExpensesView = 'money.expenses.view';
    case ExpensesCreate = 'money.expenses.create';
    case ExpensesApprove = 'money.expenses.approve';
    case SuppliersManage = 'money.suppliers.manage';
    case BudgetsView = 'money.budgets.view';
    case BudgetsManage = 'money.budgets.manage';
    case BudgetsApprove = 'money.budgets.approve';
    case BudgetActualsPost = 'money.budget_actuals.post';
    case BudgetForecastsManage = 'money.budget_forecasts.manage';
    case ExpenseClaimsView = 'money.expense_claims.view';
    case ExpenseClaimsSubmit = 'money.expense_claims.submit';
    case ExpenseClaimsApprove = 'money.expense_claims.approve';
    case ReimbursementsManage = 'money.reimbursements.manage';
    case PurchaseOrdersView = 'money.purchase_orders.view';
    case PurchaseOrdersManage = 'money.purchase_orders.manage';
    case PurchaseOrdersApprove = 'money.purchase_orders.approve';
    case PurchaseReceiptsCreate = 'money.purchase_receipts.create';
    case SupplierCreditsManage = 'money.supplier_credits.manage';
    case EInvoicesView = 'money.einvoices.view';
    case EInvoicesSend = 'money.einvoices.send';
    case EInvoiceProfilesManage = 'money.einvoice_profiles.manage';
    case WorkforceLoansView = 'money.workforce_loans.view';
    case WorkforceLoansManage = 'money.workforce_loans.manage';
    case WorkforceLoansApprove = 'money.workforce_loans.approve';
    case PosSalesCreate = 'money.pos_sales.create';
    case PosSalesVoid = 'money.pos_sales.void';
    case SalesOrdersManage = 'money.sales_orders.manage';
    case ReconciliationView = 'money.reconciliation.view';
    case ReconciliationManage = 'money.reconciliation.manage';
    case ReportsView = 'money.reports.view';
    case LedgerView = 'money.ledger.view';
    case LedgerPost = 'money.ledger.post';
    case PeriodsClose = 'money.periods.close';
    case GstView = 'money.gst.view';
    case GstPost = 'money.gst.post';
    case PayablesView = 'money.payables.view';
    case PayablesManage = 'money.payables.manage';
    case ForecastingView = 'money.forecasting.view';
    case ForecastingManage = 'money.forecasting.manage';
    case ProfitabilityView = 'money.profitability.view';
    case AccountantExportsGenerate = 'money.accountant_exports.generate';
    case AutomationView = 'money.automation.view';
    case AutomationManage = 'money.automation.manage';
    case CollectionsManage = 'money.collections.manage';
    case PaymentInstructionsSend = 'money.payment_instructions.send';
    case PaymentQrGenerate = 'money.payment_qr.generate';
    case ExceptionsManage = 'money.exceptions.manage';
    case IntegrationsManage = 'money.integrations.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_values(array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        ));
    }
}
