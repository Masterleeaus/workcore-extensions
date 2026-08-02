<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Proposals;

final readonly class ProposalEnvelope
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public array $payload,
        public string $payloadHash,
        public int $companyId,
        public int $actorId,
        public string $proposalType,
        public string $receivedAt,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'payload_hash' => $this->payloadHash,
            'company_id' => $this->companyId,
            'actor_id' => $this->actorId,
            'proposal_type' => $this->proposalType,
            'received_at' => $this->receivedAt,
        ];
    }
}
