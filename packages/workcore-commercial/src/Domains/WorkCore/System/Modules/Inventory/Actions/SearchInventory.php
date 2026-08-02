<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Actions;

use App\Domains\WorkCore\System\Modules\Inventory\Contracts\InventoryRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchInventory
{
    public function __construct(private InventoryRepositoryContract $repository) {}

    public function execute(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $tenant = workcore_tenant();
        return $this->repository->search((int) $tenant->companyId(), $filters, $perPage);
    }
}
