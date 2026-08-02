<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchWorkOrders
{
    public function __construct(
        private WorkOrderRepositoryContract $repository,
        private PermissionResolverContract $permissions,
    ) {}

    public function execute(
        ?string $query,
        ?string $status,
        ?string $customerPublicId,
        ?string $premisesPublicId,
        int $perPage = 25,
        ?string $businessLinePublicId = null,
    ): LengthAwarePaginator {
        $tenant = workcore_tenant();
        $actorId = (int) $tenant->userId();
        $companyId = (int) $tenant->companyId();
        $accessLevel = $this->permissions->accessLevel(
            $actorId,
            $companyId,
            (string) config('workcore.operations.permissions.view'),
        );
        abort_if($accessLevel === WorkCoreAccessLevel::None, 403);

        return $this->repository->search(
            companyId: $companyId,
            accessLevel: $accessLevel,
            actorId: $actorId,
            query: $query,
            status: $status,
            customerPublicId: $customerPublicId,
            premisesPublicId: $premisesPublicId,
            perPage: $perPage,
            businessLinePublicId: $businessLinePublicId,
        );
    }
}
