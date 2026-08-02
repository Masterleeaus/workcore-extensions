<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Contracts\DispatchRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchDispatchBoard
{
    public function __construct(private DispatchRepositoryContract $repository, private PermissionResolverContract $permissions) {}

    public function execute(
        ?string $query,
        ?string $status,
        ?string $assignmentType,
        ?int $assignedUserId,
        ?string $premisesPublicId,
        ?string $scheduledFrom,
        ?string $scheduledTo,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $tenant = workcore_tenant();
        $actorId = (int) $tenant->userId();
        $companyId = (int) $tenant->companyId();
        $accessLevel = $this->permissions->accessLevel($actorId, $companyId, (string) config('workcore.dispatch.permissions.view'));
        abort_if($accessLevel === WorkCoreAccessLevel::None, 403);
        return $this->repository->search(
            companyId: $companyId,
            accessLevel: $accessLevel,
            actorId: $actorId,
            query: $query,
            status: $status,
            assignmentType: $assignmentType,
            assignedUserId: $assignedUserId,
            premisesPublicId: $premisesPublicId,
            scheduledFrom: $scheduledFrom,
            scheduledTo: $scheduledTo,
            perPage: $perPage,
        );
    }
}
