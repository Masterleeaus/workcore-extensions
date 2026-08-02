<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\ReadModels;

use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use InvalidArgumentException;

final class GetTrustSummary
{
    public function __construct(private TrustAccountingRepositoryContract $repository) {}

    public function __invoke(array $filters, int $companyId, int $perPage = 25): array
    {
        return $this->repository->trustSummary($companyId, $filters);
    }
}
