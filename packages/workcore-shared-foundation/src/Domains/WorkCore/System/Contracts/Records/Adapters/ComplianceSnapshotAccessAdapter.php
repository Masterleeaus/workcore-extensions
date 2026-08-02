<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\ComplianceSnapshotAccessContract;

final class ComplianceSnapshotAccessAdapter extends AbstractDatabaseRecordAccess implements ComplianceSnapshotAccessContract
{
    protected function table(): string
    {
        return 'tz_compliance_snapshots';
    }

    protected function type(): string
    {
        return 'compliance_snapshot';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'scope',
            'subject_type',
            'subject_public_id',
            'period_start',
            'period_end',
            'summary',
            'metrics',
            'payload_hash',
            'previous_snapshot_hash',
            'status',
            'generated_at',
            'signed_off_at',
            'signoff_notes',
            'created_at',
            'updated_at',
        ];
    }
}
