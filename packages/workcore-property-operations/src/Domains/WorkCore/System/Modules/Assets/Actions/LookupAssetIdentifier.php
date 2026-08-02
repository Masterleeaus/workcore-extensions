<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Actions;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
final class LookupAssetIdentifier
{
    public function __construct(private AssetRepositoryContract $repository) {}
    public function execute(string $value, ?string $type = null): ?array
    {
        $tenant = workcore_tenant();
        return $this->repository->lookupIdentifier($value, $type, (int) $tenant->companyId());
    }
}
