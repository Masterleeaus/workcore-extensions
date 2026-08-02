<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Exceptions;

use RuntimeException;

final class AiOrchestrationException extends RuntimeException
{
    /** @param array<string,mixed> $details */
    public function __construct(string $message, int $code = 422, public readonly array $details = [])
    {
        parent::__construct($message, $code);
    }
}
