<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Premises\Contracts\PremisesRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchPremises
{
    public function __construct(private PremisesRepositoryContract $repository,private PermissionResolverContract $permissions) {}
    public function execute(?string $customerPublicId,?string $query,int $perPage=25): LengthAwarePaginator
    {
        $tenant=workcore_tenant();$actor=(int)$tenant->userId();
        $level=$this->permissions->accessLevel($actor,$tenant->companyId(),(string)config('workcore.premises.permissions.view'));
        abort_if($level===WorkCoreAccessLevel::None,403);
        return $this->repository->search($tenant->companyId(),$level,$actor,$customerPublicId,$query,$perPage);
    }
}
