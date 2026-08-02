<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Actions;

use App\Domains\WorkCore\System\Modules\Documents\Contracts\DocumentRepositoryContract;

final class SearchEvidence
{
    public function __construct(private DocumentRepositoryContract $repository) {}

    public function __invoke(array $filters, int $companyId, int $perPage = 25): mixed
    {
        return $this->repository->searchEvidence($companyId, $filters, $perPage);
    }
}
