<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\CRM\Data\SalesPipelineData;

interface SalesPipelineRepositoryContract
{
    /** @return list<array<string,mixed>> */
    public function list(int $companyId): array;

    /** @return array<string,mixed> */
    public function create(SalesPipelineData $data, int $companyId, int $actorId): array;

    /** @return array<string,mixed>|null */
    public function findByPublicId(int $companyId, string $publicId): ?array;

    /** @return array<string,mixed> */
    public function board(int $companyId, string $pipelinePublicId, WorkCoreAccessLevel $accessLevel, int $actorId): array;
}
