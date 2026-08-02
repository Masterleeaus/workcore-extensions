<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CrmActivityRepositoryContract;

final class ListCrmActivities
{
    public function __construct(private CrmActivityRepositoryContract $repository, private PermissionResolverContract $permissions) {}

    public function execute(string $subjectType, string $subjectPublicId, int $limit = 100): array
    {
        $tenant = workcore_tenant();
        $actorId = $tenant->userId();
        abort_unless($actorId !== null, 403);
        $access = $this->permissions->accessLevel($actorId, $tenant->companyId(), (string) config('workcore.crm.permissions.activities.view'));
        abort_if($access === WorkCoreAccessLevel::None, 403);
        return $this->repository->forSubject($tenant->companyId(), $subjectType, $subjectPublicId, $access, $actorId, $limit);
    }
}
