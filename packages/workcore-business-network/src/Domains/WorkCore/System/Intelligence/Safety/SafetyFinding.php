<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Safety;

final readonly class SafetyFinding
{
    public function __construct(
        public string $type,
        public string $action,
        public int $offset,
        public int $length,
        public string $replacement,
        public string $severity = 'medium',
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'action' => $this->action,
            'offset' => $this->offset,
            'length' => $this->length,
            'replacement' => $this->replacement,
            'severity' => $this->severity,
        ];
    }
}
