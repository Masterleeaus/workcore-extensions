<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Notifications;

use InvalidArgumentException;

final readonly class NotificationIntent
{
    /** @param array<string,mixed> $payload @param array<int,string> $channelHints */
    public function __construct(
        public string $key,
        public int $companyId,
        public string $recipientType,
        public string $recipientId,
        public array $payload = [],
        public array $channelHints = [],
        public ?string $idempotencyKey = null,
        public ?string $correlationId = null,
    ) {
        if ($key === '' || $companyId < 1 || $recipientType === '' || $recipientId === '') {
            throw new InvalidArgumentException('Notification key, company and recipient are required.');
        }
    }
}
