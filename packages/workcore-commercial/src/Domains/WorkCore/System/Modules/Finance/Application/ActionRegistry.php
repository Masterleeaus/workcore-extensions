<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionDefinition;
use LogicException;

final class ActionRegistry
{
    /** @var array<string, ActionDefinition> */
    private array $definitions = [];

    public function register(ActionDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("Titan Money action [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ActionDefinition
    {
        return $this->definitions[$key]
            ?? throw new LogicException("Titan Money action [{$key}] is not registered.");
    }

    /** @return array<string, ActionDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        return array_values(array_map(
            static fn (ActionDefinition $definition): array => $definition->toArray(),
            $this->definitions,
        ));
    }
}
