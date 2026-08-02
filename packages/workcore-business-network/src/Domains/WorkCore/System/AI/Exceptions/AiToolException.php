<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Exceptions;

use RuntimeException;

final class AiToolException extends RuntimeException
{
    /** @param list<string> $validationErrors */
    public function __construct(string $message, public readonly int $status = 422, public readonly array $validationErrors = [])
    {
        parent::__construct($message, $status);
    }
}
