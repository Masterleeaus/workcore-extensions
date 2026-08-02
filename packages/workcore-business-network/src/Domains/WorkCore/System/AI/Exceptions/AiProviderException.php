<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Exceptions;

use RuntimeException;

final class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = false,
        public readonly array $context = [],
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }
}
