<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class ComplianceQualityIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'compliance_quality'; }
    public function name(): string { return 'Compliance, Safety and Quality'; }
    public function capability(): string { return 'workcore.compliance'; }
    public function viewPermission(): string { return 'business.compliance.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('compliance.overdue_obligations', 'tz_compliance_obligations', 'count_date_before', 'due_on', ['today'], [
                'filters' => [['column' => 'status', 'operator' => 'not_in', 'value' => ['completed', 'waived', 'cancelled']]],
            ]),
            new DomainMetricDefinition('compliance.open_incidents', 'tz_safety_incidents', 'count_where_not', 'status', ['resolved', 'closed']),
            new DomainMetricDefinition('compliance.open_corrective_actions', 'tz_corrective_actions', 'count_where_not', 'status', ['completed', 'closed', 'cancelled']),
            new DomainMetricDefinition('compliance.low_reviews', 'tz_reviews', 'count_where', 'rating', [1, 2]),
            new DomainMetricDefinition('compliance.open_service_recoveries', 'tz_service_recovery_cases', 'count_where_not', 'status', ['resolved', 'closed', 'cancelled']),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $overdue = $this->value($metrics, 'compliance.overdue_obligations');
        if ($overdue > 0) {
            $signals[] = new DomainSignal('compliance.overdue_obligations', $overdue >= 5 ? 'critical' : 'high',
                sprintf('%d compliance obligations are overdue.', (int) $overdue), 0.98,
                $this->evidence('compliance.overdue_obligations', $overdue), 'workcore.compliance.obligation.change_status');
        }
        $incidents = $this->value($metrics, 'compliance.open_incidents');
        if ($incidents > 0) {
            $signals[] = new DomainSignal('compliance.open_incidents', 'high',
                sprintf('%d safety incidents remain open.', (int) $incidents), 0.97,
                $this->evidence('compliance.open_incidents', $incidents), 'workcore.safety.incident.change_status');
        }
        $reviews = $this->value($metrics, 'compliance.low_reviews');
        if ($reviews > 0) {
            $signals[] = new DomainSignal('quality.low_reviews', $reviews >= 5 ? 'high' : 'medium',
                sprintf('%d reviews have ratings of two stars or lower.', (int) $reviews), 0.91,
                $this->evidence('compliance.low_reviews', $reviews), 'workcore.service_recovery.create');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('workcore.safety.incident.change_status', 'Propose a reviewed safety incident status transition.', 'critical', 'business.safety.incidents.manage', true),
            new DomainActionDescriptor('workcore.compliance.obligation.change_status', 'Propose a reviewed compliance obligation status change.', 'high', 'business.compliance.status', true),
            new DomainActionDescriptor('workcore.compliance.corrective_action.create', 'Propose a corrective action from evidence.', 'medium', 'business.compliance.corrective_actions.manage', true),
            new DomainActionDescriptor('workcore.service_recovery.create', 'Propose service recovery for a substantiated quality failure.', 'medium', 'business.reviews.recovery', true),
        ];
    }
}
