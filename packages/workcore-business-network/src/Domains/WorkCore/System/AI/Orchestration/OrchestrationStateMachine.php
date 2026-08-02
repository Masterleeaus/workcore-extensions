<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;

final class OrchestrationStateMachine
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'queued' => ['running', 'cancelled', 'failed'],
        'running' => ['awaiting_approval', 'completed', 'failed', 'cancelled'],
        'awaiting_approval' => ['running', 'cancelled', 'failed'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    public function assertCanTransition(string $from, string $to): void
    {
        if (! isset(self::TRANSITIONS[$from]) || ! in_array($to, self::TRANSITIONS[$from], true)) {
            throw new AiOrchestrationException("Illegal AI orchestration transition [{$from} -> {$to}].", 409);
        }
    }
}
