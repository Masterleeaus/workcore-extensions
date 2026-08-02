<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Contracts;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\CRM\Data\CrmActivityData;

interface CrmActivityRepositoryContract
{
    /** @return array<string,mixed> */
    public function create(CrmActivityData $data, int $companyId, int $actorId): array;

    /** @return list<array<string,mixed>> */
    public function forSubject(int $companyId, string $subjectType, string $subjectPublicId, WorkCoreAccessLevel $accessLevel, int $actorId, int $limit = 100): array;
}
