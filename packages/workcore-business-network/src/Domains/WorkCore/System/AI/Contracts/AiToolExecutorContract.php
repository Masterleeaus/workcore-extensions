<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\DTO\{AiToolExecutionContext,AiToolResult};

interface AiToolExecutorContract
{
    /** @param array<string,mixed> $input */
    public function execute(string $toolName, array $input, AiToolExecutionContext $context): AiToolResult;
}
