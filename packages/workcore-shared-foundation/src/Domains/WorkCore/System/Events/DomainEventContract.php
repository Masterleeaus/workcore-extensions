<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Events;

interface DomainEventContract
{
    public function envelope(): DomainEventEnvelope;
}
