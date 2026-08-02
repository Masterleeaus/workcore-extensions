<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

use JsonException;
use LogicException;
use OutOfBoundsException;
use RuntimeException;

final class VerticalRegistry
{
    /** @var array<string,VerticalDefinition> */
    private array $definitions = [];

    public function register(VerticalDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("Vertical [{$definition->key}] is already registered.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function loadDirectory(string $path): void
    {
        if (! is_dir($path)) {
            throw new RuntimeException("Vertical manifest directory [{$path}] does not exist.");
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            try {
                $manifest = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Invalid vertical manifest [{$file}]: {$exception->getMessage()}", 0, $exception);
            }
            if (! is_array($manifest)) {
                throw new RuntimeException("Vertical manifest [{$file}] must contain a JSON object.");
            }
            $this->register(VerticalDefinition::fromArray($manifest, $file));
        }
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): VerticalDefinition
    {
        return $this->definitions[$key] ?? throw new OutOfBoundsException("Vertical [{$key}] is not registered.");
    }

    /** @return array<string,VerticalDefinition> */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return $definitions;
    }

    public function validateDependencies(): void
    {
        foreach ($this->definitions as $definition) {
            foreach ($definition->extends as $dependency) {
                if (! isset($this->definitions[$dependency])) {
                    throw new LogicException("Vertical [{$definition->key}] depends on missing pack [{$dependency}].");
                }
            }
        }

        $visiting = [];
        $visited = [];
        foreach (array_keys($this->definitions) as $key) {
            $this->visit($key, $visiting, $visited);
        }
    }

    /** @param array<string,bool> $visiting @param array<string,bool> $visited */
    private function visit(string $key, array &$visiting, array &$visited): void
    {
        if (isset($visited[$key])) {
            return;
        }
        if (isset($visiting[$key])) {
            throw new LogicException("Circular vertical dependency detected at [{$key}].");
        }

        $visiting[$key] = true;
        foreach ($this->get($key)->extends as $dependency) {
            $this->visit($dependency, $visiting, $visited);
        }
        unset($visiting[$key]);
        $visited[$key] = true;
    }
}
