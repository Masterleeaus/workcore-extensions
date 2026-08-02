<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DispatchRepositoryContract
{
    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        ?string $status = null,
        ?string $assignmentType = null,
        ?int $assignedUserId = null,
        ?string $premisesPublicId = null,
        ?string $scheduledFrom = null,
        ?string $scheduledTo = null,
        int $perPage = 25,
    ): LengthAwarePaginator;

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId, int $actorId): array;

    /** @param array<string,mixed> $data */
    public function reassign(string $dispatchPublicId, array $data, int $companyId, int $actorId): array;

    /** @param array<string,mixed> $data */
    public function changeStatus(string $dispatchPublicId, string $toStatus, ?string $reason, array $data, int $companyId, int $actorId): array;

    public function findByPublicId(int $companyId, string $publicId): ?array;
}
