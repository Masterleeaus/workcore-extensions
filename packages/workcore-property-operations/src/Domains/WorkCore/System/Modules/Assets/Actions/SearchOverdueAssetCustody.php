<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Actions;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
final class SearchOverdueAssetCustody
{
    public function __construct(private AssetRepositoryContract $repository) {}
    public function execute(array $filters = []): array
    {
        $tenant = workcore_tenant();
        return $this->repository->overdueCustody((int) $tenant->companyId(), $filters);
    }
}
