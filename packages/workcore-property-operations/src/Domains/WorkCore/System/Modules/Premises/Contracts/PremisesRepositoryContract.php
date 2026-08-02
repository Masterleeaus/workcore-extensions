<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\Premises\Data\PremisesData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PremisesRepositoryContract
{
    public function search(int $companyId, WorkCoreAccessLevel $accessLevel, int $actorId, ?string $customerPublicId = null, ?string $query = null, int $perPage = 25): LengthAwarePaginator;
    public function create(PremisesData $data, int $companyId, int $actorId): array;
    public function findByPublicId(int $companyId, string $publicId, WorkCoreAccessLevel $accessLevel = WorkCoreAccessLevel::All, ?int $actorId = null): ?array;
    public function profile(string $premisesPublicId, int $companyId): array;
    public function createSpace(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function addHazard(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function createAccessPoint(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function createServiceWindow(string $premisesPublicId, array $data, int $companyId): array;
    public function createServicePlan(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function recordMeterReading(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function registerIdentifier(string $premisesPublicId, array $data, int $companyId): array;
    public function linkContact(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function linkDocument(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function resolveHazard(string $premisesPublicId, string $hazardPublicId, array $data, int $companyId, int $actorId): array;
    public function readiness(string $premisesPublicId, int $companyId): array;
    public function approve(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
    public function archive(string $premisesPublicId, array $data, int $companyId, int $actorId): array;
}
