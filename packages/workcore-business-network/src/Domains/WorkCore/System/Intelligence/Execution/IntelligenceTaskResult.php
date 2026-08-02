<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Execution;

final readonly class IntelligenceTaskResult
{
    /** @param array<string,mixed> $data @param array<string,mixed> $usage */
    public function __construct(
        public string $status,
        public array $data,
        public array $usage = [],
        public ?FailureClassification $failure = null,
        public ?string $correlationId = null,
    ) {}

    public static function success(array $data, array $usage = [], ?string $correlationId = null): self
    {
        return new self('completed', $data, $usage, null, $correlationId);
    }

    public static function failed(FailureClassification $failure, ?string $correlationId = null): self
    {
        return new self('failed', [], [], $failure, $correlationId);
    }
}
