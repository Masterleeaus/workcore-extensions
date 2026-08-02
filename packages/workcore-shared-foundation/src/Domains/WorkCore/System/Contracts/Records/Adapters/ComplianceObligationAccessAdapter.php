<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\ComplianceObligationAccessContract;

final class ComplianceObligationAccessAdapter extends AbstractDatabaseRecordAccess implements ComplianceObligationAccessContract
{
    protected function table(): string
    {
        return 'tz_compliance_obligations';
    }

    protected function type(): string
    {
        return 'compliance_obligation';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'code',
            'title',
            'description',
            'obligation_type',
            'jurisdiction',
            'authority',
            'source_reference',
            'status',
            'effective_from',
            'effective_to',
            'review_due_on',
            'owner_user_id',
            'control_requirements',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
