<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

final readonly class AiToolResult
{
    /** @param array<string,mixed> $data @param list<string> $eventIds */
    public function __construct(
        public string $tool,
        public string $target,
        public string $kind,
        public array $data,
        public int $latencyMs,
        public ?string $auditId = null,
        public array $eventIds = [],
        public bool $replayed = false,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'target' => $this->target,
            'kind' => $this->kind,
            'data' => $this->data,
            'latency_ms' => $this->latencyMs,
            'audit_id' => $this->auditId,
            'event_ids' => $this->eventIds,
            'replayed' => $this->replayed,
        ];
    }
}
