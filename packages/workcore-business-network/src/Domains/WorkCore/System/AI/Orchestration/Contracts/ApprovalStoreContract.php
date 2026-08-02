<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Contracts;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\{ApprovalRecord,OrchestrationRunRecord,ToolCall};
use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;

interface ApprovalStoreContract
{
    public function create(OrchestrationRunRecord $run, ToolCall $call, AiToolDefinition $tool, int $expiresInMinutes): ApprovalRecord;

    public function find(int $companyId, string $approvalPublicId): ?ApprovalRecord;

    public function decide(int $companyId, string $approvalPublicId, int $actorId, string $decision, ?string $note = null): ApprovalRecord;

    public function markConsumed(int $companyId, string $approvalPublicId): void;
}
