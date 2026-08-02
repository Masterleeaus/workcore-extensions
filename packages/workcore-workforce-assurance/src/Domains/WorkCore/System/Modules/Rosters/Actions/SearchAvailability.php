<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Actions;

use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchAvailability
{
    public function __construct(private RosterRepositoryContract $repository) {}
    public function execute(?string $workerPublicId = null, ?string $availabilityType = null, ?int $weekday = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->repository->searchAvailability((int) workcore_tenant()->companyId(), $workerPublicId, $availabilityType, $weekday, $perPage);
    }
}
