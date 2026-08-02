<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

use InvalidArgumentException;

final readonly class ModelMessage
{
    /** @param array<string,mixed> $metadata @param list<array<string,mixed>> $toolCalls */
    public function __construct(
        public string $role,
        public string $content,
        public ?string $name = null,
        public ?string $toolCallId = null,
        public array $metadata = [],
        public array $toolCalls = [],
    ) {
        if (! in_array($role, ['system', 'user', 'assistant', 'tool'], true)) {
            throw new InvalidArgumentException("Invalid model-message role [{$role}].");
        }
        if ($role === 'tool' && ($toolCallId === null || trim($toolCallId) === '')) {
            throw new InvalidArgumentException('Tool messages require a tool-call identifier.');
        }
        if ($content === '' && ! ($role === 'assistant' && $toolCalls !== [])) {
            throw new InvalidArgumentException('Model-message content is required unless an assistant message contains tool calls.');
        }
        if ($toolCalls !== [] && $role !== 'assistant') {
            throw new InvalidArgumentException('Only assistant messages may contain provider tool calls.');
        }
        foreach ($toolCalls as $call) {
            if (! is_array($call) || trim((string) ($call['id'] ?? '')) === '' || trim((string) (($call['function']['name'] ?? ''))) === '') {
                throw new InvalidArgumentException('Every assistant tool call requires an ID and function name.');
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $message = ['role' => $this->role, 'content' => $this->content];
        if ($this->name !== null && $this->name !== '') {
            $message['name'] = $this->name;
        }
        if ($this->toolCallId !== null && $this->toolCallId !== '') {
            $message['tool_call_id'] = $this->toolCallId;
        }
        if ($this->toolCalls !== []) {
            $message['tool_calls'] = $this->toolCalls;
        }

        return $message;
    }
}
