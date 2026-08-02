<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\QualityAuditAccessContract;

final class QualityAuditAccessAdapter extends AbstractDatabaseRecordAccess implements QualityAuditAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_audits';
    }

    protected function type(): string
    {
        return 'quality_audit';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'reference',
            'premises_id',
            'form_template_id',
            'form_submission_id',
            'lead_auditor_user_id',
            'title',
            'audit_type',
            'scope',
            'status',
            'outcome',
            'score',
            'scheduled_for',
            'started_at',
            'completed_at',
            'signed_off_at',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
