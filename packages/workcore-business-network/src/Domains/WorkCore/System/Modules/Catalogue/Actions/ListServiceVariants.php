<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Actions;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Catalogue\Contracts\ServiceCatalogueRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;

final class ListServiceVariants
{
    public function __construct(
        private ServiceCatalogueRepositoryContract $repository,
        private TenantContextContract $tenant,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return list<array<string,mixed>> */
    public function execute(string $servicePublicId, ?int $companyId = null, ?int $actorId = null): array
    {
        $companyId ??= $this->tenant->companyId();
        $actorId ??= $this->tenant->userId();
        if ($actorId === null || ! $this->permissions->allows($actorId, $companyId, (string) config('workcore.catalogue.permissions.view'))) {
            throw new AuthorizationException('Service catalogue view permission is required.');
        }

        return $this->repository->listVariants($servicePublicId, $companyId);
    }
}
