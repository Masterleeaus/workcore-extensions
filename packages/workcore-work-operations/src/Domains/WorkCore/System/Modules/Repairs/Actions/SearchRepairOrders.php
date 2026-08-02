<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Actions;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Repairs\Contracts\RepairRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchRepairOrders
{
    public function __construct(private RepairRepositoryContract $repository,private PermissionResolverContract $permissions){}
    public function execute(array $filters=[],int $perPage=25):LengthAwarePaginator{$tenant=workcore_tenant();$level=$this->permissions->accessLevel((int)$tenant->userId(),$tenant->companyId(),(string)config('workcore.repairs.permissions.view'));abort_if($level===WorkCoreAccessLevel::None,403);return $this->repository->search($tenant->companyId(),$filters,$perPage);}
}
