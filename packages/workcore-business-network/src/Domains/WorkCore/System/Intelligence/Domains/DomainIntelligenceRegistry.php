<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use App\Domains\WorkCore\System\Intelligence\Domains\Contracts\DomainIntelligenceAdapterContract;
use LogicException;

final class DomainIntelligenceRegistry
{
    /** @var array<string,DomainIntelligenceAdapterContract> */
    private array $adapters = [];

    public function register(DomainIntelligenceAdapterContract $adapter): void
    {
        $key = $adapter->key();
        if (isset($this->adapters[$key])) {
            throw new LogicException("Domain intelligence adapter [{$key}] is already registered.");
        }
        $this->adapters[$key] = $adapter;
    }

    public function get(string $key): ?DomainIntelligenceAdapterContract
    {
        return $this->adapters[$key] ?? null;
    }

    public function require(string $key): DomainIntelligenceAdapterContract
    {
        return $this->get($key) ?? throw new LogicException("Domain intelligence adapter [{$key}] is not registered.");
    }

    /** @return array<string,DomainIntelligenceAdapterContract> */
    public function all(): array
    {
        $adapters = $this->adapters;
        ksort($adapters);

        return $adapters;
    }
}
