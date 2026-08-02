<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Premises\Contracts\PremisesRepositoryContract;

final class GetPremisesReadiness
{
    public function __construct(private PremisesRepositoryContract $repository, private PermissionResolverContract $permissions) {}

    public function execute(string $premisesPublicId): array
    {
        $tenant = workcore_tenant();
        $actor = (int) $tenant->userId();
        $level = $this->permissions->accessLevel($actor, $tenant->companyId(), (string) config('workcore.premises.permissions.view'));
        abort_if($level === WorkCoreAccessLevel::None, 403);
        abort_unless($this->repository->findByPublicId($tenant->companyId(), $premisesPublicId, $level, $actor), 404);

        return $this->repository->readiness($premisesPublicId, $tenant->companyId());
    }
}
