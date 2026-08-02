<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Actions;

use App\Domains\WorkCore\System\Modules\Assurance\Contracts\AssuranceRepositoryContract;

final class SearchIncidents
{
    public function __construct(private AssuranceRepositoryContract $repository) {}

    public function __invoke(array $filters, int $companyId, int $perPage = 25): mixed
    {
        return $this->repository->searchIncidents($companyId, $filters, $perPage);
    }
}
