<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\SalesPipelineRepositoryContract;

final class ListSalesPipelines
{
    public function __construct(private SalesPipelineRepositoryContract $repository, private PermissionResolverContract $permissions) {}

    public function execute(): array
    {
        $tenant = workcore_tenant();
        $actorId = $tenant->userId();
        abort_unless($actorId !== null, 403);
        abort_unless($this->permissions->allows($actorId, $tenant->companyId(), (string) config('workcore.crm.permissions.pipelines.view')), 403);
        return $this->repository->list($tenant->companyId());
    }
}
