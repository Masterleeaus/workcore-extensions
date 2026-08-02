<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\QualitySignoffAccessContract;

final class QualitySignoffAccessAdapter extends AbstractDatabaseRecordAccess implements QualitySignoffAccessContract
{
    protected function table(): string
    {
        return 'tz_quality_signoffs';
    }

    protected function type(): string
    {
        return 'quality_signoff';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'subject_type',
            'subject_public_id',
            'decision',
            'notes',
            'subject_hash',
            'signature_hash',
            'signed_by_user_id',
            'signed_at',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
