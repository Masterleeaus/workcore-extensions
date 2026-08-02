<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchOpportunities
{
    public function __construct(private OpportunityRepositoryContract $repository, private PermissionResolverContract $permissions) {}

    /** @param array<string,mixed> $filters */
    public function execute(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $tenant = workcore_tenant();
        $actorId = $tenant->userId();
        abort_unless($actorId !== null, 403);
        $access = $this->permissions->accessLevel($actorId, $tenant->companyId(), (string) config('workcore.crm.permissions.opportunities.view'));
        abort_if($access === WorkCoreAccessLevel::None, 403);
        return $this->repository->search($tenant->companyId(), $access, $actorId, $filters, $perPage);
    }
}
