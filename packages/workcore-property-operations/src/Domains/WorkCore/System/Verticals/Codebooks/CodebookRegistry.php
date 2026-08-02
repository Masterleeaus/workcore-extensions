<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Codebooks;

use InvalidArgumentException;
use OutOfBoundsException;

final class CodebookRegistry
{
    /** @var array<string,array{default:?string,values:array<string,string>}> */
    private array $definitions = [];

    /** @param array<string,mixed> $definitions */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_.-]*$/', $key) || ! is_array($definition)) {
                throw new InvalidArgumentException('Invalid codebook definition.');
            }
            $values = [];
            foreach ((array) ($definition['values'] ?? []) as $value => $label) {
                if (! is_string($value) || $value === '' || ! is_string($label) || trim($label) === '') {
                    throw new InvalidArgumentException("Invalid value in codebook [{$key}].");
                }
                $values[$value] = $label;
            }
            if ($values === []) {
                throw new InvalidArgumentException("Codebook [{$key}] must register at least one value.");
            }
            $default = isset($definition['default']) ? (string) $definition['default'] : null;
            if ($default !== null && ! isset($values[$default])) {
                throw new InvalidArgumentException("Default [{$default}] is not registered in codebook [{$key}].");
            }
            $this->definitions[$key] = ['default' => $default, 'values' => $values];
        }
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /** @return array{default:?string,values:array<string,string>} */
    public function get(string $key): array
    {
        return $this->definitions[$key] ?? throw new OutOfBoundsException("Codebook [{$key}] is not registered.");
    }

    /** @param list<string> $values */
    public function assertKnown(string $key, array $values): void
    {
        $registered = $this->get($key)['values'];
        foreach ($values as $value) {
            if (! isset($registered[$value])) {
                throw new InvalidArgumentException("Value [{$value}] is not registered in codebook [{$key}].");
            }
        }
    }

    public function label(string $key, string $value): string
    {
        $definition = $this->get($key);
        return $definition['values'][$value] ?? $value;
    }
}
