<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\ReadModels;

use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use InvalidArgumentException;

final class GetTrustMatterLedger
{
    public function __construct(private TrustAccountingRepositoryContract $repository) {}

    public function __invoke(array $filters, int $companyId, int $perPage = 25): array
    {
        $publicId = (string) ($filters['matter_public_id'] ?? ''); if ($publicId === '') throw new InvalidArgumentException('Trust matter is required.'); return $this->repository->matterLedger($publicId, $companyId);
    }
}
