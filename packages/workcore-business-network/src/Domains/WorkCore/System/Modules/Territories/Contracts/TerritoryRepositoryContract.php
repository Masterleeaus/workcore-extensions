<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TerritoryRepositoryContract
{
    public function createRegion(array $data, int $companyId, int $actorId): array;
    public function createDistrict(array $data, int $companyId, int $actorId): array;
    public function createBranch(array $data, int $companyId, int $actorId): array;
    public function createTerritory(array $data, int $companyId, int $actorId): array;
    public function assign(array $data, int $companyId, int $actorId): array;
    public function search(int $companyId, WorkCoreAccessLevel $level, int $actorId, array $filters, int $perPage = 25): LengthAwarePaginator;
    public function match(int $companyId, ?string $postcode, ?string $suburb, ?float $latitude, ?float $longitude, int $limit = 20): array;
    public function findByPublicId(int $companyId, string $publicId): ?array;
}
