<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

final readonly class ApprovalRecord
{
    /** @param array<string,mixed> $toolInput */
    public function __construct(
        public string $publicId,
        public int $companyId,
        public int $requestedByActorId,
        public string $runPublicId,
        public string $conversationPublicId,
        public string $toolCallId,
        public string $toolName,
        public array $toolInput,
        public string $risk,
        public string $status,
        public ?int $decidedByActorId,
        public ?string $decisionNote,
        public bool $consumed,
        public int $expiresAtEpoch,
    ) {}

    public function isExpired(?int $now = null): bool
    {
        return ($now ?? time()) >= $this->expiresAtEpoch;
    }

    public function with(
        ?string $status = null,
        ?int $decidedByActorId = null,
        ?string $decisionNote = null,
        ?bool $consumed = null,
    ): self {
        return new self(
            $this->publicId,
            $this->companyId,
            $this->requestedByActorId,
            $this->runPublicId,
            $this->conversationPublicId,
            $this->toolCallId,
            $this->toolName,
            $this->toolInput,
            $this->risk,
            $status ?? $this->status,
            $decidedByActorId ?? $this->decidedByActorId,
            $decisionNote ?? $this->decisionNote,
            $consumed ?? $this->consumed,
            $this->expiresAtEpoch,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'run_public_id' => $this->runPublicId,
            'conversation_public_id' => $this->conversationPublicId,
            'tool_call_id' => $this->toolCallId,
            'tool_name' => $this->toolName,
            'tool_input' => $this->toolInput,
            'risk' => $this->risk,
            'status' => $this->status,
            'consumed' => $this->consumed,
            'expires_at_epoch' => $this->expiresAtEpoch,
        ];
    }
}
