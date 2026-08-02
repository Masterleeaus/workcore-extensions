<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use RuntimeException;

final class InspectExpansionRequest
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $requestPublicId): array
    {
        [$companyId, $actorId] = $this->context();
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.view')), 403);

        return $this->repository->findRequest($companyId, trim($requestPublicId))
            ?? throw new RuntimeException('Capability expansion request was not found.');
    }

    /** @return array{int,int} */
    private function context(): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        return [$companyId, $actorId];
    }
}
