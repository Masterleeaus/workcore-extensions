<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\LeadRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchLeads
{
    public function __construct(
        private LeadRepositoryContract $repo,
        private PermissionResolverContract $permissions,
    ) {}

    public function execute(?string $query, int $perPage = 25): LengthAwarePaginator
    {
        $tenant = workcore_tenant();
        $actorId = $tenant->userId();
        abort_unless($actorId !== null, 403);

        $accessLevel = $this->permissions->accessLevel(
            $actorId,
            $tenant->companyId(),
            (string) config('workcore.crm.permissions.leads.view'),
        );
        abort_if($accessLevel === WorkCoreAccessLevel::None, 403);

        return $this->repo->search($tenant->companyId(), $accessLevel, $actorId, $query, $perPage);
    }
}
