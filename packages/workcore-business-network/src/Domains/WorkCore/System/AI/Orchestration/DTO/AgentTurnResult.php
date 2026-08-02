<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use InvalidArgumentException;

final readonly class AgentTurnResult
{
    /** @param list<array<string,mixed>> $toolResults @param array<string,mixed> $metadata */
    public function __construct(
        public string $status,
        public string $conversationPublicId,
        public string $orchestrationRunPublicId,
        public ?string $content = null,
        public ?string $messagePublicId = null,
        public ?string $approvalPublicId = null,
        public array $toolResults = [],
        public array $metadata = [],
    ) {
        if (! in_array($status, ['completed', 'awaiting_approval', 'blocked', 'failed'], true)) {
            throw new InvalidArgumentException("Invalid agent-turn status [{$status}].");
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'conversation_public_id' => $this->conversationPublicId,
            'orchestration_run_public_id' => $this->orchestrationRunPublicId,
            'content' => $this->content,
            'message_public_id' => $this->messagePublicId,
            'approval_public_id' => $this->approvalPublicId,
            'tool_results' => $this->toolResults,
            'metadata' => $this->metadata,
        ];
    }
}
