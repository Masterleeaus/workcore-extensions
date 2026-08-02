<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RosterRepositoryContract
{
    public function searchRosters(int $companyId, ?string $query = null, ?string $status = null, ?string $rosterType = null, ?string $premisesPublicId = null, ?string $startsFrom = null, ?string $startsTo = null, int $perPage = 25): LengthAwarePaginator;
    public function searchAvailability(int $companyId, ?string $workerPublicId = null, ?string $availabilityType = null, ?int $weekday = null, int $perPage = 50): LengthAwarePaginator;
    public function createAvailabilityRule(array $data, int $companyId, int $actorId): array;
    public function createRoster(array $data, int $companyId, int $actorId): array;
    public function createShift(array $data, int $companyId, int $actorId): array;
    public function assignShift(string $shiftPublicId, array $data, int $companyId, int $actorId): array;
    public function publishRoster(string $rosterPublicId, array $data, int $companyId, int $actorId): array;
    public function findRosterByPublicId(int $companyId, string $publicId): ?array;
}
