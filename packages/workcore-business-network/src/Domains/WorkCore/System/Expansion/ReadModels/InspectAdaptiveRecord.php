<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use RuntimeException;

final class InspectAdaptiveRecord
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $recordPublicId): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.records_view')), 403);

        return $this->repository->findAdaptiveRecord($companyId, trim($recordPublicId))
            ?? throw new RuntimeException('Adaptive record was not found.');
    }
}
