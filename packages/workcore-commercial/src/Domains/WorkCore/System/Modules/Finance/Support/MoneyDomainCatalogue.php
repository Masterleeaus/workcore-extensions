<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Support;

final readonly class MoneyDomainCatalogue
{
    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        return [
            'budgeting' => [
                'capability' => 'workcore.money.budgeting',
                'tables' => ['tm_budgets', 'tm_budget_lines', 'tm_budget_actuals', 'tm_budget_variances', 'tm_budget_forecast_scenarios'],
                'integrates_with' => ['finance', 'premises', 'operations'],
            ],
            'expense_claims' => [
                'capability' => 'workcore.money.expense_claims',
                'tables' => ['tm_expense_claims', 'tm_expense_claim_receipts', 'tm_reimbursement_batches', 'tm_reimbursements'],
                'integrates_with' => ['finance', 'workforce', 'operations'],
            ],
            'procurement' => [
                'capability' => 'workcore.money.procurement',
                'tables' => ['tm_purchase_orders', 'tm_purchase_order_lines', 'tm_purchase_receipts', 'tm_purchase_receipt_lines', 'tm_supplier_credits'],
                'integrates_with' => ['finance', 'inventory', 'assets', 'premises', 'operations'],
            ],
            'einvoicing' => [
                'capability' => 'workcore.money.einvoicing',
                'tables' => ['tm_einvoice_profiles', 'tm_einvoice_transmissions', 'tm_einvoice_events'],
                'integrates_with' => ['finance', 'crm'],
            ],
            'workforce_loans' => [
                'capability' => 'workcore.money.workforce_loans',
                'tables' => ['tm_workforce_loans', 'tm_workforce_loan_repayments'],
                'integrates_with' => ['finance', 'workforce', 'attendance'],
            ],
            'sales' => [
                'capability' => 'workcore.money.sales',
                'tables' => ['tm_pos_sales', 'tm_pos_sale_lines', 'tm_pos_sale_payments', 'tm_sales_orders', 'tm_sales_order_lines'],
                'integrates_with' => ['finance', 'catalogue', 'inventory', 'operations', 'crm'],
            ],
        ];
    }
}
