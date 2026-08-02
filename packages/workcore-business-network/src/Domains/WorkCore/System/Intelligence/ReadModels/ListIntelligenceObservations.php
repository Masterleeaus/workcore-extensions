<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Observations\ObservationRepositoryContract;

final class ListIntelligenceObservations
{
    public function __construct(
        private ObservationRepositoryContract $observations,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return list<array<string,mixed>> */
    public function execute(?string $domain = null, int $limit = 50): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.permissions.view')), 403);

        return $this->observations->listForCompany($companyId, $domain, $limit);
    }
}
