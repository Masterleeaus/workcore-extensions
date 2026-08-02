<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Actions;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Fleet\Contracts\FleetRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchFleetVehicles
{
    public function __construct(private FleetRepositoryContract $repository,private PermissionResolverContract $permissions){}
    public function execute(array $filters=[],int $perPage=25):LengthAwarePaginator{$tenant=workcore_tenant();$level=$this->permissions->accessLevel((int)$tenant->userId(),$tenant->companyId(),(string)config('workcore.fleet.permissions.view'));abort_if($level===WorkCoreAccessLevel::None,403);return $this->repository->search($tenant->companyId(),$filters,$perPage);}
}
