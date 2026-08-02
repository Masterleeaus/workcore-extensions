<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

use InvalidArgumentException;

final readonly class AiToolExecutionContext
{
    public function __construct(
        public int $companyId,
        public int $actorId,
        public ?string $aiRunPublicId = null,
        public string $channel = 'internal',
        public string $agent = '*',
        public ?string $confirmationId = null,
        public ?string $idempotencyKey = null,
        public string $source = 'workcore.native_ai',
    ) {
        if ($companyId < 1 || $actorId < 1) {
            throw new InvalidArgumentException('Company and actor are required for AI tool execution.');
        }
        if (trim($channel) === '' || trim($source) === '') {
            throw new InvalidArgumentException('AI tool channel and source are required.');
        }
    }
}
