<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

final readonly class ModelResponse
{
    /** @param array<string,mixed> $structured @param list<array<string,mixed>> $toolCalls @param array<string,mixed> $metadata */
    public function __construct(
        public ?string $content,
        public array $structured,
        public array $toolCalls,
        public TokenUsage $usage,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public ?string $providerResponseId = null,
        public array $metadata = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'structured' => $this->structured,
            'tool_calls' => $this->toolCalls,
            'usage' => $this->usage->toArray(),
            'provider' => $this->provider,
            'model' => $this->model,
            'latency_ms' => $this->latencyMs,
            'provider_response_id' => $this->providerResponseId,
            'metadata' => $this->metadata,
        ];
    }
}
