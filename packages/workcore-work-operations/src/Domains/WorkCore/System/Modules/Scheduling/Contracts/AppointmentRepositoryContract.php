<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AppointmentRepositoryContract
{
    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        ?string $status = null,
        ?string $appointmentType = null,
        ?string $customerPublicId = null,
        ?string $premisesPublicId = null,
        ?string $workOrderPublicId = null,
        ?string $startsFrom = null,
        ?string $startsTo = null,
        int $perPage = 25,
    ): LengthAwarePaginator;

    /** @param array<string,mixed> $data */
    public function create(array $data, int $companyId, int $actorId): array;

    /** @param array<string,mixed> $data */
    public function reschedule(string $appointmentPublicId, array $data, int $companyId, int $actorId): array;

    public function changeStatus(string $appointmentPublicId, string $toStatus, ?string $reason, int $companyId, int $actorId): array;

    public function findByPublicId(int $companyId, string $publicId): ?array;
}
