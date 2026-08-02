<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

use InvalidArgumentException;

final readonly class ActionRequest
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $key,
        public array $payload,
        public int $companyId,
        public int $actorId,
        public string $idempotencyKey,
        public ?string $confirmationId = null,
        public string $source = 'internal',
    ) {
        if ($key === '' || $companyId < 1 || $actorId < 1 || trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Action key, company, actor and idempotency key are required.');
        }
    }

    public function payloadHash(): string
    {
        $payload = $this->payload;
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
