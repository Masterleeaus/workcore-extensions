<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class PremisesAssetsIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'premises_assets'; }
    public function name(): string { return 'Premises, Assets and Inspections'; }
    public function capability(): string { return 'workcore.premises'; }
    public function viewPermission(): string { return 'business.premises.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('premises.premises', 'tz_premises'),
            new DomainMetricDefinition('premises.assets', 'tz_assets'),
            new DomainMetricDefinition('premises.assets_maintenance_due', 'tz_assets', 'count_date_before', 'next_service_due_on', ['today'], [
                'filters' => [['column' => 'status', 'operator' => 'not_in', 'value' => ['retired', 'disposed', 'written_off']]],
            ]),
            new DomainMetricDefinition('premises.failed_forms', 'tz_form_failures', 'count_where', 'status', ['open']),
            new DomainMetricDefinition('premises.open_hazards', 'tz_hazards', 'count_where_not', 'status', ['resolved', 'closed']),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $maintenance = $this->value($metrics, 'premises.assets_maintenance_due');
        if ($maintenance > 0) {
            $signals[] = new DomainSignal('premises.asset_maintenance_due', $maintenance >= 10 ? 'high' : 'medium',
                sprintf('%d assets are past their service due date.', (int) $maintenance), 0.96,
                $this->evidence('premises.assets_maintenance_due', $maintenance), 'workcore.asset.maintenance.start');
        }
        $failures = $this->value($metrics, 'premises.failed_forms');
        if ($failures > 0) {
            $signals[] = new DomainSignal('premises.failed_inspections', $failures >= 5 ? 'high' : 'medium',
                sprintf('%d form or inspection failures remain open.', (int) $failures), 0.95,
                $this->evidence('premises.failed_forms', $failures), 'workcore.form_failure.convert_to_work_order');
        }
        $hazards = $this->value($metrics, 'premises.open_hazards');
        if ($hazards > 0) {
            $signals[] = new DomainSignal('premises.open_hazards', 'high',
                sprintf('%d premises hazards remain unresolved.', (int) $hazards), 0.97,
                $this->evidence('premises.open_hazards', $hazards), 'workcore.premises.hazard.resolve');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('workcore.asset.maintenance.start', 'Propose starting overdue asset maintenance.', 'medium', 'business.assets.maintenance', true),
            new DomainActionDescriptor('workcore.form_failure.convert_to_work_order', 'Propose corrective work from a failed inspection.', 'medium', 'business.forms.failures.convert', true),
            new DomainActionDescriptor('workcore.premises.hazard.resolve', 'Propose a governed hazard resolution after evidence review.', 'high', 'business.premises.update', true),
        ];
    }
}
