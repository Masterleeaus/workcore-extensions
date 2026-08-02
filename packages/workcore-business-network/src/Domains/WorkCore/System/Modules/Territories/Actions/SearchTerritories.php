<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Territories\Contracts\TerritoryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchTerritories
{
    public function __construct(private TerritoryRepositoryContract $repository, private PermissionResolverContract $permissions) {}
    public function execute(array $filters, int $perPage=25): LengthAwarePaginator
    {
        $tenant=workcore_tenant(); $actor=(int)$tenant->userId();
        $level=$this->permissions->accessLevel($actor,$tenant->companyId(),(string)config('workcore.territories.permissions.view'));
        abort_if($level===WorkCoreAccessLevel::None,403);
        return $this->repository->search($tenant->companyId(),$level,$actor,$filters,$perPage);
    }
}
