<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Capabilities;

use LogicException;

final class CapabilityRegistry
{
    /** @var array<string,CapabilityDefinition> */
    private array $definitions = [];

    public function register(CapabilityDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("Capability [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ?CapabilityDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return array<string,CapabilityDefinition> */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return $definitions;
    }
}
