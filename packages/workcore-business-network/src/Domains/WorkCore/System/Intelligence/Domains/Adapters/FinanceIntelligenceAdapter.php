<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class FinanceIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'finance'; }
    public function name(): string { return 'Finance and Money'; }
    public function capability(): string { return 'workcore.finance'; }
    public function viewPermission(): string { return 'money.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('finance.open_invoices', 'tm_invoices', 'count_where_not', 'state', ['paid', 'voided', 'cancelled']),
            new DomainMetricDefinition('finance.overdue_invoices', 'tm_invoices', 'count_date_before', 'due_date', ['today'], [
                'filters' => [
                    ['column' => 'state', 'operator' => 'not_in', 'value' => ['paid', 'voided', 'cancelled']],
                    ['column' => 'balance_minor', 'operator' => '>', 'value' => 0],
                ],
            ]),
            new DomainMetricDefinition('finance.overdue_amount_minor', 'tm_invoices', 'sum_date_between', 'due_date', ['2000-01-01', 'today'], [
                'sum_column' => 'balance_minor',
                'filters' => [
                    ['column' => 'state', 'operator' => 'not_in', 'value' => ['paid', 'voided', 'cancelled']],
                    ['column' => 'balance_minor', 'operator' => '>', 'value' => 0],
                ],
            ]),
            new DomainMetricDefinition('finance.unallocated_payments', 'tm_payments', 'count_column_compare', 'allocated_minor', [], [
                'compare_column' => 'amount_minor', 'compare_operator' => '<',
                'filters' => [['column' => 'state', 'operator' => 'in', 'value' => ['confirmed', 'settled']]],
            ]),
            new DomainMetricDefinition('finance.month_expense_minor', 'tm_expenses', 'sum_date_between', 'expense_date', ['first day of this month', 'last day of this month'], [
                'sum_column' => 'total_minor',
                'filters' => [['column' => 'state', 'operator' => 'not_in', 'value' => ['draft', 'rejected', 'voided']]],
            ]),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $overdue = $this->value($metrics, 'finance.overdue_invoices');
        $amount = $this->value($metrics, 'finance.overdue_amount_minor');
        if ($overdue > 0) {
            $signals[] = new DomainSignal('finance.overdue_receivables', $amount >= 1000000 ? 'critical' : ($amount >= 250000 ? 'high' : 'medium'),
                sprintf('%d invoices are overdue with %d minor currency units outstanding.', (int) $overdue, (int) $amount), 0.97,
                array_merge($this->evidence('finance.overdue_invoices', $overdue), $this->evidence('finance.overdue_amount_minor', $amount)),
                'money.collection.execute_followup');
        }
        $unallocated = $this->value($metrics, 'finance.unallocated_payments');
        if ($unallocated > 0) {
            $signals[] = new DomainSignal('finance.unallocated_payments', 'high',
                sprintf('%d confirmed or settled payments are not fully allocated.', (int) $unallocated), 0.98,
                $this->evidence('finance.unallocated_payments', $unallocated), 'money.confirm_payment_match');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('money.collection.execute_followup', 'Propose a policy-safe collection follow-up.', 'medium', 'money.collections.manage', true),
            new DomainActionDescriptor('money.confirm_payment_match', 'Propose confirmation of a ranked payment match.', 'high', 'money.payments.confirm', true),
            new DomainActionDescriptor('money.create_invoice_from_job', 'Propose invoicing a completed job.', 'medium', 'money.invoices.draft', true),
        ];
    }
}
