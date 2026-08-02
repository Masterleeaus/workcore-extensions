<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;

final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var list<OutboxEvent> */ public array $events = [];
    public function append(OutboxEvent $event): void { $this->events[] = $event; }
}
