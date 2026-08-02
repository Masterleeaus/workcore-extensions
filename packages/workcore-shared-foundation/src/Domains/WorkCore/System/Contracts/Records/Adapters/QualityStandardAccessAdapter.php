<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\QualityStandardAccessContract;

final class QualityStandardAccessAdapter extends AbstractDatabaseRecordAccess implements QualityStandardAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_standards';
    }

    protected function type(): string
    {
        return 'quality_standard';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'code',
            'name',
            'standard_type',
            'version',
            'status',
            'description',
            'effective_from',
            'effective_to',
            'review_due_on',
            'pass_score',
            'requirements',
            'created_at',
            'updated_at',
        ];
    }
}
