<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\SalesPipelineRepositoryContract;

final class ViewSalesPipelineBoard
{
    public function __construct(private SalesPipelineRepositoryContract $repository, private PermissionResolverContract $permissions) {}

    public function execute(string $pipelinePublicId): array
    {
        $tenant = workcore_tenant();
        $actorId = $tenant->userId();
        abort_unless($actorId !== null, 403);
        $access = $this->permissions->accessLevel($actorId, $tenant->companyId(), (string) config('workcore.crm.permissions.opportunities.view'));
        abort_if($access === WorkCoreAccessLevel::None, 403);
        return $this->repository->board($tenant->companyId(), $pipelinePublicId, $access, $actorId);
    }
}
