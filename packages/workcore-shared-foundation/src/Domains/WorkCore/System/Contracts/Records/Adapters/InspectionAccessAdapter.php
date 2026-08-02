<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\InspectionAccessContract;

final class InspectionAccessAdapter extends AbstractDatabaseRecordAccess implements InspectionAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_inspections';
    }

    protected function type(): string
    {
        return 'quality_inspection';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'standard_id',
            'form_template_id',
            'form_submission_id',
            'premises_id',
            'work_order_id',
            'appointment_id',
            'asset_id',
            'subject_worker_id',
            'inspector_user_id',
            'inspection_type',
            'status',
            'outcome',
            'score',
            'pass_score',
            'critical_failure_count',
            'scheduled_for',
            'started_at',
            'completed_at',
            'signed_off_at',
            'notes',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
