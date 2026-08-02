<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use InvalidArgumentException;

final readonly class ToolCall
{
    /** @param array<string,mixed> $arguments */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {
        if (trim($id) === '' || ! preg_match('/^[a-z][a-z0-9_.-]{2,127}$/', $name)) {
            throw new InvalidArgumentException('A valid tool-call ID and native tool name are required.');
        }
    }
}
