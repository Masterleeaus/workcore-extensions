<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\ReadModels;

use InvalidArgumentException;

final readonly class ReadModelDefinition
{
    /** @param class-string $handler @param array<string,mixed> $metadata */
    public function __construct(
        public string $key,
        public string $handler,
        public ?string $capability = null,
        public array $metadata = [],
        public ?string $permission = null,
    ) {
        if ($key === '' || $handler === '') {
            throw new InvalidArgumentException('Read-model key and handler are required.');
        }
    }
}
