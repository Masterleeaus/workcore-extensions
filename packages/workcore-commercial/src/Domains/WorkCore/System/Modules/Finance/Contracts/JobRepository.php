<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface JobRepository
{
    /** @return array<string, mixed>|null */
    public function findForCompany(string $companyId, string $jobId): ?array;
}
