<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Events;

use DateTimeImmutable;

final readonly class OutboxRelayRecord
{
    /** @param array<string,mixed> $payload */
    public function __construct(public string $id,public string $companyId,public string $eventType,public string $aggregateType,public string $aggregateId,public array $payload,public string $correlationId,public DateTimeImmutable $occurredAt,public int $publishAttempts=0){}
}
