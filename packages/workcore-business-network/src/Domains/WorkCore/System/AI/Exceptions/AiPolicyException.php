<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Exceptions;

use RuntimeException;

final class AiPolicyException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 429)
    {
        parent::__construct($message, $status);
    }
}
