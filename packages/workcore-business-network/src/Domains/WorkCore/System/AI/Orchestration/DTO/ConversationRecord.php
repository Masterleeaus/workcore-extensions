<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

final readonly class ConversationRecord
{
    public function __construct(
        public string $publicId,
        public int $companyId,
        public int $actorId,
        public string $channel,
        public ?string $agentPublicId,
        public string $status = 'active',
        public ?string $title = null,
    ) {}
}
