<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Services;

final class EvidenceSignoffPolicy
{
    public function isSignable(array $evidence): bool
    {
        $status = strtolower(trim((string) ($evidence['status'] ?? '')));
        $checksum = strtolower(trim((string) ($evidence['checksum_sha256'] ?? '')));

        return in_array($status, ['active', 'verified'], true)
            && preg_match('/^[a-f0-9]{64}$/', $checksum) === 1;
    }
}
