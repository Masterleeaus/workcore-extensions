<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\QualityNonconformanceAccessContract;

final class QualityNonconformanceAccessAdapter extends AbstractDatabaseRecordAccess implements QualityNonconformanceAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_nonconformances';
    }

    protected function type(): string
    {
        return 'quality_nonconformance';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'reference',
            'inspection_id',
            'audit_id',
            'form_failure_id',
            'support_ticket_id',
            'service_recovery_id',
            'category',
            'severity',
            'status',
            'title',
            'description',
            'containment_action',
            'root_cause',
            'owner_user_id',
            'due_at',
            'closed_at',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
