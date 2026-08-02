<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Actions;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchAssets
{
    public function __construct(private AssetRepositoryContract $repository) {}
    public function execute(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $tenant = workcore_tenant();
        return $this->repository->search((int) $tenant->companyId(), $filters, $perPage);
    }
}
