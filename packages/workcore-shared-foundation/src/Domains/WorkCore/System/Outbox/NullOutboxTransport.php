<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Outbox;

use RuntimeException;

final class NullOutboxTransport implements OutboxTransportContract
{
    public function publish(OutboxMessage $message): void
    {
        throw new RuntimeException('No WorkCore outbox transport is configured. Bind OutboxTransportContract to Titan Signal, Pulse, Rewind, analytics, notification, or integration transport.');
    }
}
