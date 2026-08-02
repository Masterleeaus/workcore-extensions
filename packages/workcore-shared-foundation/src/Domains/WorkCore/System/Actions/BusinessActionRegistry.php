<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

use LogicException;

final class BusinessActionRegistry
{
    /** @var array<string,ActionDefinition> */
    private array $definitions = [];

    public function register(ActionDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("Business action [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ?ActionDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return array<string,ActionDefinition> */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return $definitions;
    }
}
