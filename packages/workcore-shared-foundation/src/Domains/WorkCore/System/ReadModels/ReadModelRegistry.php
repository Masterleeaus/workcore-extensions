<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\ReadModels;

use LogicException;

final class ReadModelRegistry
{
    /** @var array<string,ReadModelDefinition> */
    private array $definitions = [];

    public function register(ReadModelDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("Read model [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ?ReadModelDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return array<string,ReadModelDefinition> */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return $definitions;
    }
}
