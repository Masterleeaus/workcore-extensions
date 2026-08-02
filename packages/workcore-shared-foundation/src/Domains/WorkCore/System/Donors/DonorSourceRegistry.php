<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Donors;

use InvalidArgumentException;

final class DonorSourceRegistry
{
    /** @var array<string, DonorSourceDefinition> */
    private array $sources = [];

    public function register(DonorSourceDefinition $definition): void
    {
        if ($definition->key === '' || isset($this->sources[$definition->key])) {
            throw new InvalidArgumentException("Duplicate or empty WorkCore donor source [{$definition->key}].");
        }

        $this->sources[$definition->key] = $definition;
    }

    public function get(string $key): DonorSourceDefinition
    {
        return $this->sources[$key] ?? throw new InvalidArgumentException("Unknown WorkCore donor source [{$key}].");
    }

    /** @return array<string, DonorSourceDefinition> */
    public function all(): array
    {
        ksort($this->sources);

        return $this->sources;
    }
}
