<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Actions;

use App\Domains\WorkCore\System\Modules\Inventory\Contracts\InventoryRepositoryContract;

final class ListStockLocations
{
    public function __construct(private InventoryRepositoryContract $repository) {}

    public function execute(array $filters = []): array
    {
        $tenant = workcore_tenant();
        return $this->repository->locations((int) $tenant->companyId(), $filters);
    }
}
