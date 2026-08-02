<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\ApprovalDecision;
use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;

final class ToolApprovalPolicy
{
    public function decide(AiToolDefinition $tool, ?string $confirmationId = null): ApprovalDecision
    {
        if ($tool->risk === 'critical') {
            return new ApprovalDecision('block', 'Critical WorkCore tools cannot be executed by AI orchestration.');
        }

        if ($confirmationId !== null && trim($confirmationId) !== '') {
            return new ApprovalDecision('execute', 'A recorded WorkCore confirmation is present.');
        }

        if ($tool->requiresConfirmation || $tool->risk === 'high') {
            return new ApprovalDecision('pause', 'The authoritative WorkCore tool definition requires explicit approval.');
        }

        return new ApprovalDecision('execute', 'The authoritative WorkCore tool definition permits immediate execution.');
    }
}
