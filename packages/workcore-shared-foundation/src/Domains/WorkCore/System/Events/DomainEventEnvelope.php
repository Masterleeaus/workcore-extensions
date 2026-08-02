<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Events;

use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final readonly class DomainEventEnvelope
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $eventId,
        public string $eventName,
        public int $eventVersion,
        public int $companyId,
        public ?int $actorId,
        public TypedReference $aggregate,
        public string $occurredAt,
        public string $correlationId,
        public ?string $causationId,
        public array $payload = [],
    ) {
        if (trim($eventId) === '' || trim($eventName) === '' || trim($occurredAt) === '' || trim($correlationId) === '') {
            throw new InvalidArgumentException('Event identity, name, occurrence time, and correlation ID are required.');
        }
        if ($eventVersion < 1 || $companyId < 1 || ($actorId !== null && $actorId < 1)) {
            throw new InvalidArgumentException('Event version and identity values are invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'event_version' => $this->eventVersion,
            'company_id' => $this->companyId,
            'actor_id' => $this->actorId,
            'aggregate' => $this->aggregate->toArray(),
            'occurred_at' => $this->occurredAt,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'payload' => $this->payload,
        ];
    }
}
