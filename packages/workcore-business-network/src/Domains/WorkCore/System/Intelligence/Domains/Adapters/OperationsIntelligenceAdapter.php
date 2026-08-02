<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class OperationsIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'operations'; }
    public function name(): string { return 'Jobs, Scheduling and Dispatch'; }
    public function capability(): string { return 'workcore.operations'; }
    public function viewPermission(): string { return 'business.work_orders.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('operations.open_work_orders', 'tz_work_orders', 'count_where_not', 'status', ['completed', 'cancelled']),
            new DomainMetricDefinition('operations.overdue_work_orders', 'tz_work_orders', 'count_date_before', 'due_at', ['now'], [
                'filters' => [['column' => 'status', 'operator' => 'not_in', 'value' => ['completed', 'cancelled']]],
            ]),
            new DomainMetricDefinition('operations.unassigned_appointments', 'tz_dispatch_assignments', 'count_where_null', 'assigned_user_id', [], [
                'filters' => [['column' => 'status', 'operator' => 'not_in', 'value' => ['completed', 'cancelled', 'failed']]],
            ]),
            new DomainMetricDefinition('operations.active_dispatch_assignments', 'tz_dispatch_assignments', 'count_where_not', 'status', ['completed', 'cancelled', 'failed']),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $overdue = $this->value($metrics, 'operations.overdue_work_orders');
        if ($overdue > 0) {
            $signals[] = new DomainSignal('operations.overdue_work', $overdue >= 10 ? 'critical' : ($overdue >= 3 ? 'high' : 'medium'),
                sprintf('%d work orders are overdue.', (int) $overdue), 0.96,
                $this->evidence('operations.overdue_work_orders', $overdue), 'workcore.work_order.change_status');
        }
        $unassigned = $this->value($metrics, 'operations.unassigned_appointments');
        if ($unassigned > 0) {
            $signals[] = new DomainSignal('operations.unassigned_appointments', $unassigned >= 5 ? 'high' : 'medium',
                sprintf('%d active dispatch assignments have no worker.', (int) $unassigned), 0.94,
                $this->evidence('operations.unassigned_appointments', $unassigned), 'workcore.dispatch.assign');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('workcore.work_order.change_status', 'Propose a reviewed status transition for overdue work.', 'high', 'business.work_orders.status', true),
            new DomainActionDescriptor('workcore.dispatch.assign', 'Propose assignment of an eligible worker.', 'medium', 'business.dispatch.assign', true),
            new DomainActionDescriptor('workcore.appointment.reschedule', 'Propose a schedule adjustment.', 'medium', 'business.appointments.update', true),
            new DomainActionDescriptor('workcore.work_order.create', 'Propose a new work order from an observed operational need.', 'medium', 'business.work_orders.create', true),
        ];
    }
}
