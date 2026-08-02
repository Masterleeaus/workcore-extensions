<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

use App\Domains\WorkCore\System\References\TypedReference;

final readonly class ActionHandlerResult
{
    /** @param array<string,mixed> $data @param array<int,PendingDomainEvent> $events */
    public function __construct(
        public array $data,
        public ?TypedReference $aggregate = null,
        public array $events = [],
    ) {}
}
