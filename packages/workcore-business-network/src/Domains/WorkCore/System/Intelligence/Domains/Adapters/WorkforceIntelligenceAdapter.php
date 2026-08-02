<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class WorkforceIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'workforce'; }
    public function name(): string { return 'Workforce, Credentials and Attendance'; }
    public function capability(): string { return 'workcore.workforce'; }
    public function viewPermission(): string { return 'business.workers.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('workforce.active_workers', 'tz_workers', 'count_where', 'employment_status', ['active']),
            new DomainMetricDefinition('workforce.expiring_certifications', 'tz_worker_certifications', 'count_date_between', 'expires_on', ['today', '+30 days']),
            new DomainMetricDefinition('workforce.expired_certifications', 'tz_worker_certifications', 'count_date_before', 'expires_on', ['today']),
            new DomainMetricDefinition('workforce.open_attendance_sessions', 'tz_attendance_sessions', 'count_where_null', 'clocked_out_at'),
            new DomainMetricDefinition('workforce.pending_leave_requests', 'tz_leave_requests', 'count_where', 'status', ['pending', 'submitted']),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $expired = $this->value($metrics, 'workforce.expired_certifications');
        if ($expired > 0) {
            $signals[] = new DomainSignal('workforce.expired_credentials', 'critical',
                sprintf('%d worker certifications are expired.', (int) $expired), 0.98,
                $this->evidence('workforce.expired_certifications', $expired), 'workcore.worker.add_certification');
        }
        $expiring = $this->value($metrics, 'workforce.expiring_certifications');
        if ($expiring > 0) {
            $signals[] = new DomainSignal('workforce.expiring_credentials', $expiring >= 5 ? 'high' : 'medium',
                sprintf('%d worker certifications expire within 30 days.', (int) $expiring), 0.95,
                $this->evidence('workforce.expiring_certifications', $expiring), 'workcore.worker.add_certification');
        }
        $open = $this->value($metrics, 'workforce.open_attendance_sessions');
        if ($open > 0) {
            $signals[] = new DomainSignal('workforce.open_attendance', $open >= 5 ? 'high' : 'medium',
                sprintf('%d attendance sessions remain open.', (int) $open), 0.92,
                $this->evidence('workforce.open_attendance_sessions', $open), 'workcore.attendance.clock_out');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('workcore.worker.add_certification', 'Propose credential renewal or replacement evidence.', 'medium', 'business.workers.certifications.manage', true),
            new DomainActionDescriptor('workcore.attendance.clock_out', 'Propose resolution of an anomalous open attendance session.', 'high', 'business.attendance.clock', true),
            new DomainActionDescriptor('workcore.leave_request.change_status', 'Propose review of pending leave.', 'medium', 'business.leave.approve', true),
        ];
    }
}
