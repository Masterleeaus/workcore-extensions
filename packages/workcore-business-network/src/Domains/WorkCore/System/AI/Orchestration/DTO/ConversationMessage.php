<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use App\Domains\WorkCore\System\AI\DTO\ModelMessage;

final readonly class ConversationMessage
{
    /** @param list<array<string,mixed>> $toolCalls @param array<string,mixed> $metadata */
    public function __construct(
        public string $publicId,
        public int $sequence,
        public string $role,
        public string $content,
        public ?string $name = null,
        public ?string $toolCallId = null,
        public array $toolCalls = [],
        public array $metadata = [],
    ) {}

    public function toModelMessage(): ModelMessage
    {
        return new ModelMessage(
            role: $this->role,
            content: $this->content,
            name: $this->name,
            toolCallId: $this->toolCallId,
            metadata: $this->metadata,
            toolCalls: $this->toolCalls,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'sequence' => $this->sequence,
            'role' => $this->role,
            'content' => $this->content,
            'name' => $this->name,
            'tool_call_id' => $this->toolCallId,
            'tool_calls' => $this->toolCalls,
            'metadata' => $this->metadata,
        ];
    }
}
