<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

use InvalidArgumentException;

final readonly class ActionDefinition
{
    /** @param class-string $handler @param array<string,mixed> $metadata */
    public function __construct(
        public string $key,
        public string $handler,
        public string $risk = 'low',
        public bool $requiresConfirmation = false,
        public ?string $capability = null,
        public ?string $permission = null,
        public array $metadata = [],
    ) {
        if ($key === '' || $handler === '') {
            throw new InvalidArgumentException('Action key and handler are required.');
        }
        if (! in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException("Invalid action risk [{$risk}].");
        }
    }
}
