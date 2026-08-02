<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Idempotency;

final readonly class IdempotencyClaim
{
    /** @param array<string, mixed>|null $resultPayload */
    private function __construct(
        public IdempotencyClaimStatus $status,
        public ?array $resultPayload = null,
    ) {
    }

    public static function acquired(): self
    {
        return new self(IdempotencyClaimStatus::Acquired);
    }

    /** @param array<string, mixed> $resultPayload */
    public static function replay(array $resultPayload): self
    {
        return new self(IdempotencyClaimStatus::Replay, $resultPayload);
    }

    public static function inProgress(): self
    {
        return new self(IdempotencyClaimStatus::InProgress);
    }

    public static function conflict(): self
    {
        return new self(IdempotencyClaimStatus::Conflict);
    }

    /** @return array{status:string,result_payload:array<string,mixed>|null} */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'result_payload' => $this->resultPayload,
        ];
    }
}
