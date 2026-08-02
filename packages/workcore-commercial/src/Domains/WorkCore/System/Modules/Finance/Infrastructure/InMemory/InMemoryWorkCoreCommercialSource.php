<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\WorkCoreCommercialSource;

final readonly class InMemoryWorkCoreCommercialSource implements WorkCoreCommercialSource
{
    /** @param array<string,mixed> $job @param list<array<string,mixed>> $lines @param array<string,mixed> $costs */
    public function __construct(private array $job, private array $lines, private array $costs = []) {}
    public function completedJobForCompany(string $companyId, string $jobId): ?array { return ($this->job['id']??null)===$jobId ? $this->job : null; }
    public function billableLinesForJob(string $companyId, string $jobId): array { return $this->lines; }
    public function costSummaryForJob(string $companyId, string $jobId): array { return $this->costs; }
}
