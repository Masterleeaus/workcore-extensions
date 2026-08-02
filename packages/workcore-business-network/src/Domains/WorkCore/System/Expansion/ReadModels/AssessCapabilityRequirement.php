<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Services\CapabilityGapAnalyzer;
use App\Domains\WorkCore\System\Expansion\Services\CapabilityInventoryBuilder;
use InvalidArgumentException;

final class AssessCapabilityRequirement
{
    public function __construct(
        private CapabilityInventoryBuilder $inventory,
        private CapabilityGapAnalyzer $analyzer,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $requirement): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.view')), 403);
        if (strlen(trim($requirement)) < 8) {
            throw new InvalidArgumentException('A meaningful capability requirement is required.');
        }

        return $this->analyzer->assess($requirement, $this->inventory->build($companyId))->toArray();
    }
}
