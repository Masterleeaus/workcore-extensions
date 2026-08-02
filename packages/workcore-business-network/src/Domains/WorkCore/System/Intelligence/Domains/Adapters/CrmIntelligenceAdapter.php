<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class CrmIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'crm'; }
    public function name(): string { return 'Customers and CRM'; }
    public function capability(): string { return 'workcore.crm'; }
    public function viewPermission(): string { return 'business.customers.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('crm.customers', 'tz_customers'),
            new DomainMetricDefinition('crm.leads_requiring_follow_up', 'tz_leads', 'count_where', 'next_follow_up', [1]),
            new DomainMetricDefinition('crm.open_opportunities', 'tz_opportunities', 'count_where', 'status', ['open']),
            new DomainMetricDefinition('crm.overdue_opportunities', 'tz_opportunities', 'count_date_before', 'expected_close_date', ['today'], [
                'filters' => [['column' => 'status', 'operator' => '=', 'value' => 'open']],
            ]),
            new DomainMetricDefinition('crm.pipeline_value_minor', 'tz_opportunities', 'sum_where', 'status', ['open'], [
                'sum_column' => 'amount', 'multiplier' => 100,
            ]),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $followUps = $this->value($metrics, 'crm.leads_requiring_follow_up');
        if ($followUps > 0) {
            $signals[] = new DomainSignal('crm.follow_up_backlog', $followUps >= 10 ? 'high' : 'medium',
                sprintf('%d leads require follow-up.', (int) $followUps), 0.93,
                $this->evidence('crm.leads_requiring_follow_up', $followUps), 'workcore.crm_activity.create');
        }
        $overdue = $this->value($metrics, 'crm.overdue_opportunities');
        if ($overdue > 0) {
            $signals[] = new DomainSignal('crm.opportunity_overdue', $overdue >= 5 ? 'high' : 'medium',
                sprintf('%d open opportunities are past their expected close date.', (int) $overdue), 0.9,
                $this->evidence('crm.overdue_opportunities', $overdue), 'workcore.opportunity.update');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('workcore.crm_activity.create', 'Propose a customer or lead follow-up activity.', 'low', 'business.crm_activities.create', false),
            new DomainActionDescriptor('workcore.opportunity.update', 'Propose an opportunity update after review.', 'medium', 'business.opportunities.update', true),
            new DomainActionDescriptor('workcore.lead.convert', 'Propose conversion of a qualified lead.', 'high', 'business.leads.convert', true),
        ];
    }
}
