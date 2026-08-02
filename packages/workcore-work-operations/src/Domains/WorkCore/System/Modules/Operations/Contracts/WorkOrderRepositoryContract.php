<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WorkOrderRepositoryContract
{
    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        ?string $status = null,
        ?string $customerPublicId = null,
        ?string $premisesPublicId = null,
        int $perPage = 25,
        ?string $businessLinePublicId = null,
    ): LengthAwarePaginator;

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId, int $actorId): array;

    public function changeStatus(string $workOrderPublicId, string $toStatus, ?string $reason, int $companyId, int $actorId): array;

    public function updateTaskStatus(string $taskPublicId, string $toStatus, int $companyId, int $actorId): array;

    /** @param array<string,mixed> $data */
    public function addTimeEntry(string $workOrderPublicId, array $data, int $companyId, int $actorId): array;

    public function findByPublicId(int $companyId, string $publicId): ?array;
}
