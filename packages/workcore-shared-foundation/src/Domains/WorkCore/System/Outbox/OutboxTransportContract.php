<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Outbox;

interface OutboxTransportContract
{
    public function publish(OutboxMessage $message): void;
}
