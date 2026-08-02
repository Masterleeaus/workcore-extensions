<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Actions;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchSuppliers
{
    public function __construct(private SupplyRepositoryContract $repository,private PermissionResolverContract $permissions){}
    public function execute(array $filters=[],int $perPage=25):LengthAwarePaginator{$tenant=workcore_tenant();$level=$this->permissions->accessLevel((int)$tenant->userId(),$tenant->companyId(),(string)config('workcore.supply.permissions.view'));abort_if($level===WorkCoreAccessLevel::None,403);return $this->repository->searchSuppliers($tenant->companyId(),$filters,$perPage);}
}
