<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Contracts;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\{AgentTurnRequest,ConversationMessage,ConversationRecord,ResolvedAgentProfile};

interface ConversationStoreContract
{
    public function findOrCreate(AgentTurnRequest $request, ResolvedAgentProfile $agent): ConversationRecord;

    /** @param list<array<string,mixed>> $toolCalls @param array<string,mixed> $metadata */
    public function append(
        int $companyId,
        string $conversationPublicId,
        string $role,
        string $content,
        ?string $name = null,
        ?string $toolCallId = null,
        array $toolCalls = [],
        array $metadata = [],
    ): ConversationMessage;

    /** @return list<ConversationMessage> */
    public function history(int $companyId, string $conversationPublicId, int $limit): array;

    public function find(int $companyId, string $conversationPublicId): ?ConversationRecord;

    /** @return list<ConversationRecord> */
    public function listForActor(int $companyId, int $actorId, int $limit = 50): array;
}
