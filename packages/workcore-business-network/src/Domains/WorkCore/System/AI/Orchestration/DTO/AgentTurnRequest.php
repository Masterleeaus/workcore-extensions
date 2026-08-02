<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use InvalidArgumentException;

final readonly class AgentTurnRequest
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $companyId,
        public int $actorId,
        public string $text,
        public ?string $conversationPublicId = null,
        public ?string $agentPublicId = null,
        public string $channel = 'internal',
        public string $source = 'workcore.native_ai',
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {
        if ($companyId < 1 || $actorId < 1 || trim($text) === '') {
            throw new InvalidArgumentException('Company, actor and non-empty user text are required.');
        }
        if (! preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $channel)) {
            throw new InvalidArgumentException("Invalid AI conversation channel [{$channel}].");
        }
    }
}
