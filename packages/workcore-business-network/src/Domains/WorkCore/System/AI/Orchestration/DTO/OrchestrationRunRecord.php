<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

final readonly class OrchestrationRunRecord
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $publicId,
        public int $companyId,
        public int $actorId,
        public string $conversationPublicId,
        public ?string $agentPublicId,
        public string $status,
        public int $stepCount = 0,
        public ?string $pendingApprovalPublicId = null,
        public array $metadata = [],
    ) {}

    /** @param array<string,mixed>|null $metadata */
    public function with(
        ?string $status = null,
        ?int $stepCount = null,
        ?string $pendingApprovalPublicId = null,
        ?array $metadata = null,
    ): self {
        return new self(
            $this->publicId,
            $this->companyId,
            $this->actorId,
            $this->conversationPublicId,
            $this->agentPublicId,
            $status ?? $this->status,
            $stepCount ?? $this->stepCount,
            $pendingApprovalPublicId,
            $metadata ?? $this->metadata,
        );
    }
}
