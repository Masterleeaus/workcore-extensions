<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\QualityCorrectiveActionAccessContract;

final class QualityCorrectiveActionAccessAdapter extends AbstractDatabaseRecordAccess implements QualityCorrectiveActionAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_corrective_actions';
    }

    protected function type(): string
    {
        return 'quality_corrective_action';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'nonconformance_id',
            'action_type',
            'status',
            'description',
            'owner_user_id',
            'due_at',
            'completed_at',
            'verification_method',
            'verified_at',
            'effectiveness',
            'evidence_reference',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
