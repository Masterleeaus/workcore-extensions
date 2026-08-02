<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\External;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Database\ConnectionInterface;

final class ExternalCandidateLookup
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContextContract $tenant,
    ) {}

    /** @return array<string,mixed>|null */
    public function find(int $companyId, string $entityType, string $publicId): ?array
    {
        if ($companyId !== $this->tenant->companyId()) {
            throw new \LogicException('Candidate lookup must use the active WorkCore company.');
        }

        $table = match ($entityType) {
            'lead' => 'tz_leads',
            'customer' => 'tz_customers',
            'supplier', 'provider', 'contractor' => 'tz_suppliers',
            default => null,
        };
        if ($table === null || trim($publicId) === '') {
            return null;
        }

        $record = $this->db->table($table)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->first();

        return $record === null ? null : (array) $record;
    }
}
