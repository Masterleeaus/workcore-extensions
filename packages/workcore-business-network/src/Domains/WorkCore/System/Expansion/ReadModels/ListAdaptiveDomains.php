<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;

final class ListAdaptiveDomains
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function execute(?string $status = null): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.view')), 403);

        return $this->repository->listAdaptiveDomains($companyId, $status);
    }
}
