<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\QualityIncidentAccessContract;

final class QualityIncidentAccessAdapter extends AbstractDatabaseRecordAccess implements QualityIncidentAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_incidents';
    }

    protected function type(): string
    {
        return 'quality_incident';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'reference',
            'premises_id',
            'work_order_id',
            'appointment_id',
            'asset_id',
            'worker_id',
            'incident_type',
            'severity',
            'status',
            'title',
            'description',
            'occurred_at',
            'reported_at',
            'is_reportable',
            'regulator_reference',
            'immediate_actions',
            'resolution',
            'resolved_at',
            'evidence_reference',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
