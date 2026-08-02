<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use RuntimeException;

final class ListAdaptiveDomainVersions
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function execute(string $domain, int $limit = 50): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.view')), 403);
        $definition = $this->repository->findAdaptiveDomain($companyId, trim($domain))
            ?? throw new RuntimeException('Adaptive domain was not found.');

        return $this->repository->listAdaptiveDomainVersions($companyId, $definition, $limit);
    }
}
