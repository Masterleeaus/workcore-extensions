<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Actions;

use App\Domains\WorkCore\System\Modules\Assurance\Contracts\AssuranceRepositoryContract;

final class GetAssuranceSummary
{
    public function __construct(private AssuranceRepositoryContract $repository) {}

    public function __invoke(array $filters, int $companyId, int $perPage = 25): array
    {
        return $this->repository->summary($companyId);
    }
}
