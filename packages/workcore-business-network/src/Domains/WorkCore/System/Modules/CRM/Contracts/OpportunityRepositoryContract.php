<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\CRM\Data\OpportunityData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OpportunityRepositoryContract
{
    /** @param array<string,mixed> $filters */
    public function search(int $companyId, WorkCoreAccessLevel $accessLevel, int $actorId, array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /** @return array<string,mixed> */
    public function create(OpportunityData $data, int $companyId, int $actorId): array;

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function update(int $companyId, string $publicId, int $actorId, array $data): array;

    /** @return array<string,mixed> */
    public function move(int $companyId, string $publicId, int $actorId, string $stagePublicId, int $position): array;

    /** @return array<string,mixed> */
    public function markWon(int $companyId, string $publicId, int $actorId): array;

    /** @return array<string,mixed> */
    public function markLost(int $companyId, string $publicId, int $actorId, ?string $reason): array;

    /** @return array<string,mixed>|null */
    public function findByPublicId(int $companyId, string $publicId, WorkCoreAccessLevel $accessLevel = WorkCoreAccessLevel::All, ?int $actorId = null): ?array;

    /** @return array<string,mixed> */
    public function forecast(int $companyId, WorkCoreAccessLevel $accessLevel, int $actorId, ?string $pipelinePublicId = null): array;
}
