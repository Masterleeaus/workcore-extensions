<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Execution;

final readonly class FailureClassification
{
    public function __construct(
        public string $category,
        public bool $retryable,
        public string $code,
        public string $safeMessage,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'retryable' => $this->retryable,
            'code' => $this->code,
            'safe_message' => $this->safeMessage,
        ];
    }
}
