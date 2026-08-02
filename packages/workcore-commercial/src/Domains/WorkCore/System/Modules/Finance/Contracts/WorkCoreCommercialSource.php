<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface WorkCoreCommercialSource
{
    public function completedJobForCompany(string $companyId, string $jobId): ?array;
    public function billableLinesForJob(string $companyId, string $jobId): array;
    public function costSummaryForJob(string $companyId, string $jobId): array;
}
