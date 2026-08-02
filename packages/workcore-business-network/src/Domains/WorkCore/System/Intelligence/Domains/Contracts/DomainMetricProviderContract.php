<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Contracts;

use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;

interface DomainMetricProviderContract
{
    /**
     * @param list<DomainMetricDefinition> $definitions
     * @return array<string,int|float|null>
     */
    public function measure(int $companyId, array $definitions): array;
}
