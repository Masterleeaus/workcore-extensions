<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinanceRepositoryContract;

final class GetFinanceSummary
{
    public function __construct(private FinanceRepositoryContract $repository) {}
    public function __invoke(array $filters, int $companyId, int $perPage = 25): mixed
    {
        return $this->repository->financeSummary($companyId);
    }
}
