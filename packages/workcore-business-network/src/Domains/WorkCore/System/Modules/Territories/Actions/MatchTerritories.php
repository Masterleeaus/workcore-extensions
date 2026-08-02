<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Territories\Actions;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Territories\Contracts\TerritoryRepositoryContract;
final class MatchTerritories
{
 public function __construct(private TerritoryRepositoryContract $repository,private PermissionResolverContract $permissions){}
 public function execute(?string $postcode,?string $suburb,?float $latitude,?float $longitude,int $limit=20): array
 { $t=workcore_tenant();$level=$this->permissions->accessLevel((int)$t->userId(),$t->companyId(),(string)config('workcore.territories.permissions.view'));abort_if($level===WorkCoreAccessLevel::None,403);return $this->repository->match($t->companyId(),$postcode,$suburb,$latitude,$longitude,$limit); }
}
