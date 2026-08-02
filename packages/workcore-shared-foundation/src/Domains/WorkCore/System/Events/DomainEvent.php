<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Events;

abstract class DomainEvent
{
    abstract public function aggregateId(): string;

    abstract public function aggregateType(): string;

    abstract public function eventType(): string;

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return get_object_vars($this);
    }
}
