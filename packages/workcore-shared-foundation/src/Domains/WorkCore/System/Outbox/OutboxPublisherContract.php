<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Outbox;

interface OutboxPublisherContract
{
    /** @return array{published:int,retried:int,failed:int} */
    public function publishPending(int $limit = 100): array;
}
