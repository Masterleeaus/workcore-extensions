<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Actions;

use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchRosters
{
    public function __construct(private RosterRepositoryContract $repository) {}
    public function execute(?string $query = null, ?string $status = null, ?string $rosterType = null, ?string $premisesPublicId = null, ?string $startsFrom = null, ?string $startsTo = null, int $perPage = 25): LengthAwarePaginator
    {
        return $this->repository->searchRosters((int) workcore_tenant()->companyId(), $query, $status, $rosterType, $premisesPublicId, $startsFrom, $startsTo, $perPage);
    }
}
