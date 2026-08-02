<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\CRM\Data\CustomerData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryContract
{
    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        int $perPage = 25,
    ): LengthAwarePaginator;

    public function create(CustomerData $data, int $companyId, int $actorId): array;

    public function findByPublicId(
        int $companyId,
        string $publicId,
        WorkCoreAccessLevel $accessLevel = WorkCoreAccessLevel::All,
        ?int $actorId = null,
    ): ?array;
}
