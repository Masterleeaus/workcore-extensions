<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Outbox;

final readonly class OutboxMessage
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $messageId,
        public ?int $companyId,
        public string $topic,
        public ?string $eventId,
        public string $correlationId,
        public ?string $causationId,
        public array $payload,
        public int $attempts,
    ) {}
}
