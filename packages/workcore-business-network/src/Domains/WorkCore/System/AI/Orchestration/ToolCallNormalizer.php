<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\ToolCall;
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use JsonException;

final class ToolCallNormalizer
{
    /** @param array<string,mixed> $raw */
    public function normalize(array $raw): ToolCall
    {
        $id = trim((string) ($raw['id'] ?? ''));
        $function = is_array($raw['function'] ?? null) ? $raw['function'] : [];
        $name = trim((string) ($function['name'] ?? ''));
        $arguments = $function['arguments'] ?? [];

        if (is_string($arguments)) {
            try {
                $decoded = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new AiOrchestrationException('The model returned malformed JSON tool arguments.', 422, [
                    'tool_call_id' => $id,
                    'tool_name' => $name,
                    'json_error' => $exception->getMessage(),
                ]);
            }
            if (! is_array($decoded)) {
                throw new AiOrchestrationException('AI tool arguments must decode to an object.', 422);
            }
            $arguments = $decoded;
        }

        if (! is_array($arguments)) {
            throw new AiOrchestrationException('AI tool arguments must be an object.', 422);
        }

        try {
            return new ToolCall($id, $name, $arguments);
        } catch (\InvalidArgumentException $exception) {
            throw new AiOrchestrationException($exception->getMessage(), 422);
        }
    }
}
