<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinanceRepositoryContract;

final class GetQuoteProfile
{
    public function __construct(private FinanceRepositoryContract $repository) {}
    public function __invoke(array $filters, int $companyId, int $perPage = 25): mixed
    {
        $id = trim((string) ($filters['quote_id'] ?? $filters['quote_public_id'] ?? '')); if ($id === '') throw new \InvalidArgumentException('quote_id is required.'); return $this->repository->quoteProfile($id, $companyId);
    }
}
