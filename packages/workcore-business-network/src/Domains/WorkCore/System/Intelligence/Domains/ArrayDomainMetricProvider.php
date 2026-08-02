<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use App\Domains\WorkCore\System\Intelligence\Domains\Contracts\DomainMetricProviderContract;

final readonly class ArrayDomainMetricProvider implements DomainMetricProviderContract
{
    /** @param array<string,int|float|null> $values */
    public function __construct(private array $values) {}

    public function measure(int $companyId, array $definitions): array
    {
        $measured = [];
        foreach ($definitions as $definition) {
            if (array_key_exists($definition->key, $this->values)) {
                $measured[$definition->key] = $this->values[$definition->key];
            }
        }

        return $measured;
    }
}
