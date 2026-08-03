<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Entitlements;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementRevisionResolverContract;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use Illuminate\Database\ConnectionInterface;

final class ProjectedCompanyEntitlementResolver implements EntitlementResolverContract, EntitlementRevisionResolverContract
{
    /** @param list<string> $bootstrapCapabilities */
    public function __construct(
        private ConnectionInterface $db,
        private CapabilityRegistry $capabilities,
        private array $bootstrapCapabilities = [],
    ) {}

    public function allows(int $companyId, ?string $capability): bool
    {
        if ($capability === null) {
            return true;
        }
        $capability = trim($capability);
        if ($companyId < 1 || $capability === '' || ! $this->capabilities->has($capability)) {
            return false;
        }
        if (in_array($capability, $this->bootstrapCapabilities, true)) {
            return true;
        }

        $state = $this->db->table('tz_company_entitlement_states')
            ->where('company_id', $companyId)
            ->first(['revision', 'valid_until']);
        if ($state === null) {
            return false;
        }
        if ($state->valid_until !== null && strtotime((string) $state->valid_until) <= time()) {
            return false;
        }

        $enabled = $this->db->table('tz_company_entitlement_projections')
            ->where('company_id', $companyId)
            ->where('capability_key', $capability)
            ->where('revision', (int) $state->revision)
            ->value('enabled');

        return (bool) $enabled;
    }

    public function revision(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }

        return (int) ($this->db->table('tz_company_entitlement_states')
            ->where('company_id', $companyId)
            ->value('revision') ?? 0);
    }
}
