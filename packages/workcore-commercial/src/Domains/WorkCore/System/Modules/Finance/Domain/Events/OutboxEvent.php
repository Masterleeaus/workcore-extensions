<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Events;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class OutboxEvent
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $id,
        public string $companyId,
        public string $eventType,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public ActionContext $context,
        public int $aggregateVersion = 1,
        public ?string $causationId = null,
    ) {
        foreach ([$id, $companyId, $eventType, $aggregateType, $aggregateId] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Outbox event identity values must not be blank.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'event_type' => $this->eventType,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'aggregate_version' => $this->aggregateVersion,
            'payload' => $this->payload,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'causation_id' => $this->causationId,
            'context' => $this->context->toArray(),
        ];
    }
}
