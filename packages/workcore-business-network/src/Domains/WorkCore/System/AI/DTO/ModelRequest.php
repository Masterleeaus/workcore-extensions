<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

use InvalidArgumentException;

final readonly class ModelRequest
{
    /**
     * @param list<ModelMessage> $messages
     * @param list<array<string,mixed>> $tools
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public int $companyId,
        public int $actorId,
        public array $messages,
        public string $purpose = 'chat',
        public ?string $model = null,
        public ?string $system = null,
        public array $tools = [],
        public string $responseFormat = 'text',
        public float $temperature = 0.2,
        public int $maxOutputTokens = 1024,
        public ?string $threadPublicId = null,
        public ?string $agentPublicId = null,
        public array $metadata = [],
    ) {
        if ($companyId < 1 || $actorId < 1) {
            throw new InvalidArgumentException('Company and actor are required for every model request.');
        }
        if ($messages === [] && ($system === null || trim($system) === '')) {
            throw new InvalidArgumentException('A system prompt or at least one message is required.');
        }
        foreach ($messages as $message) {
            if (! $message instanceof ModelMessage) {
                throw new InvalidArgumentException('Every message must be a ModelMessage.');
            }
        }
        if ($maxOutputTokens < 1 || $maxOutputTokens > 32768) {
            throw new InvalidArgumentException('maxOutputTokens must be between 1 and 32768.');
        }
        if ($temperature < 0 || $temperature > 2) {
            throw new InvalidArgumentException('temperature must be between 0 and 2.');
        }
        if (! in_array($responseFormat, ['text', 'json', 'tool_call'], true)) {
            throw new InvalidArgumentException("Unsupported response format [{$responseFormat}].");
        }
    }

    /** @return array<string,mixed> */
    public function toProviderPayload(): array
    {
        $messages = array_map(static fn (ModelMessage $message): array => $message->toArray(), $this->messages);
        if ($this->system !== null && trim($this->system) !== '') {
            array_unshift($messages, ['role' => 'system', 'content' => $this->system]);
        }

        $payload = [
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxOutputTokens,
        ];
        if ($this->model !== null && $this->model !== '') {
            $payload['model'] = $this->model;
        }
        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
            $payload['tool_choice'] = 'auto';
            $payload['parallel_tool_calls'] = false;
        }
        if ($this->responseFormat === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }
}
