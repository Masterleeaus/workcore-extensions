<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\DTO\AiToolExecutionContext;
use App\Domains\WorkCore\System\AI\DTO\AiToolResult;
use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;
use Throwable;

interface AiToolRunRecorderContract
{
    /** @param array<string,mixed> $input */
    public function start(AiToolDefinition $tool, array $input, AiToolExecutionContext $context): string;
    public function complete(string $toolRunPublicId, AiToolResult $result): void;
    public function fail(string $toolRunPublicId, Throwable $exception): void;
}
