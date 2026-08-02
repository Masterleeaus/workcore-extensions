<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Actions;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
final class GetAssetAvailability
{
    public function __construct(private AssetRepositoryContract $repository) {}
    public function execute(string $assetPublicId): array
    {
        $tenant = workcore_tenant();
        return $this->repository->availability($assetPublicId, (int) $tenant->companyId());
    }
}
