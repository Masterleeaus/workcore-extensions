<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\References;

use InvalidArgumentException;

final class RecordTypeRegistry
{
    /** @var array<string,true> */
    private array $types = [];

    /** @param list<string> $types */
    public function __construct(array $types = [])
    {
        foreach ($types as $type) { $this->register($type); }
    }

    public function register(string $type): void
    {
        $reference = new TypedReference($type, 'validation');
        $this->types[$reference->type] = true;
    }

    public function allows(string $type): bool { return isset($this->types[$type]); }

    public function assertAllowed(string $type): void
    {
        if (! $this->allows($type)) {
            throw new InvalidArgumentException("Unknown WorkCore record type [{$type}].");
        }
    }

    public function reference(string $type, string $id): TypedReference
    {
        $this->assertAllowed($type);
        return new TypedReference($type, $id);
    }

    /** @return list<string> */
    public function all(): array { return array_keys($this->types); }
}
