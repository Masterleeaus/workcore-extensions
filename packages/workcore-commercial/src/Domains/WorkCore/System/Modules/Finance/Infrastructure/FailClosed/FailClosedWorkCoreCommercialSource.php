<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\WorkCoreCommercialSource;
use RuntimeException;

final class FailClosedWorkCoreCommercialSource implements WorkCoreCommercialSource
{
    public function completedJobForCompany(string $companyId,string $jobId): ?array { throw new RuntimeException('Bind the Platform Core WorkCoreCommercialSource adapter before using automatic invoicing.'); }
    public function billableLinesForJob(string $companyId,string $jobId): array { throw new RuntimeException('Bind the Platform Core WorkCoreCommercialSource adapter before using automatic invoicing.'); }
    public function costSummaryForJob(string $companyId,string $jobId): array { throw new RuntimeException('Bind the Platform Core WorkCoreCommercialSource adapter before using automatic invoicing.'); }
}
