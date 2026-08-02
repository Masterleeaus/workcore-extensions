<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\WorkerCredentialAccessContract;

final class WorkerCredentialAccessAdapter extends AbstractDatabaseRecordAccess implements WorkerCredentialAccessContract
{
    protected function table(): string
    {
        return 'tz_worker_credentials';
    }

    protected function type(): string
    {
        return 'worker_credential';
    }

    protected function columns(): array
    {
        return [
            'public_id',
            'worker_id',
            'credential_type',
            'name',
            'issuer',
            'reference_number',
            'issued_on',
            'expires_on',
            'verification_status',
            'status',
            'verified_at',
            'verification_notes',
            'evidence_reference',
            'requirements',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }
}
