<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

use LogicException;

final class AiToolRegistry
{
    /** @var array<string,AiToolDefinition> */
    private array $definitions = [];

    public function register(AiToolDefinition $definition): void
    {
        if (isset($this->definitions[$definition->name])) {
            throw new LogicException("AI tool [{$definition->name}] is already registered.");
        }
        $this->definitions[$definition->name] = $definition;
    }

    /** @param iterable<AiToolDefinition> $definitions */
    public function registerMany(iterable $definitions): void
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function get(string $name): ?AiToolDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /** @return array<string,AiToolDefinition> */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);
        return $definitions;
    }
}
