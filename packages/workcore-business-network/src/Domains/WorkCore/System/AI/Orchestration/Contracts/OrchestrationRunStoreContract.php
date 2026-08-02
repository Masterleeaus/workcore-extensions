<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Contracts;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\{AgentTurnRequest,ConversationRecord,OrchestrationRunRecord,ResolvedAgentProfile};

interface OrchestrationRunStoreContract
{
    public function start(AgentTurnRequest $request, ConversationRecord $conversation, ResolvedAgentProfile $agent): OrchestrationRunRecord;

    /** @param array<string,mixed> $input @param array<string,mixed> $output */
    public function recordStep(string $runPublicId, string $type, string $status, array $input = [], array $output = []): void;

    /** @param array<string,mixed> $metadata */
    public function transition(string $runPublicId, string $status, array $metadata = []): OrchestrationRunRecord;

    public function find(int $companyId, string $runPublicId): ?OrchestrationRunRecord;

    public function findByIdempotency(int $companyId, string $idempotencyKey): ?OrchestrationRunRecord;
}
