<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

final readonly class ActionResult
{
    /** @param array<string,mixed> $data @param array<int,string> $eventIds */
    public function __construct(
        public string $actionKey,
        public array $data,
        public string $correlationId,
        public string $auditId,
        public array $eventIds = [],
        public bool $replayed = false,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'action' => $this->actionKey,
            'data' => $this->data,
            'correlation_id' => $this->correlationId,
            'audit_id' => $this->auditId,
            'event_ids' => $this->eventIds,
            'replayed' => $this->replayed,
        ];
    }
}
