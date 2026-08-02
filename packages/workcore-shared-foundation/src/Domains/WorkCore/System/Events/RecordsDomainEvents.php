<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Events;

trait RecordsDomainEvents
{
    /** @var list<DomainEventEnvelope> */
    private array $recordedDomainEvents = [];

    protected function recordDomainEvent(DomainEventEnvelope $event): void { $this->recordedDomainEvents[] = $event; }

    /** @return list<DomainEventEnvelope> */
    public function releaseDomainEvents(): array
    {
        $events = $this->recordedDomainEvents;
        $this->recordedDomainEvents = [];
        return $events;
    }
}
