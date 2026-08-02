<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher,BusinessActionRegistry};
use App\Domains\WorkCore\System\AI\DTO\{AiToolExecutionContext,AiToolResult};
use App\Domains\WorkCore\System\AI\Exceptions\AiToolException;

final class WorkCoreActionToolExecutor
{
    public function __construct(
        private BusinessActionRegistry $actions,
        private BusinessActionDispatcher $dispatcher,
        private JsonSchemaValidator $validator,
    ) {}

    /** @param array<string,mixed> $input */
    public function execute(AiToolDefinition $tool, array $input, AiToolExecutionContext $context): AiToolResult
    {
        if ($tool->kind !== 'action' || ! $this->actions->has($tool->target)) {
            throw new AiToolException("AI tool [{$tool->name}] does not target a registered WorkCore action.", 404);
        }
        $errors = $this->validator->validate($input, $tool->inputSchema);
        if ($errors !== []) {
            throw new AiToolException('AI tool input validation failed.', 422, $errors);
        }
        if ($tool->requiresConfirmation && ($context->confirmationId === null || trim($context->confirmationId) === '')) {
            throw new AiToolException("AI tool [{$tool->name}] requires explicit WorkCore confirmation.", 428);
        }

        $started = hrtime(true);
        $idempotencyKey = $context->idempotencyKey ?: hash('sha256', implode('|', [
            $context->aiRunPublicId ?? 'standalone', $tool->name, json_encode($input, JSON_THROW_ON_ERROR),
        ]));
        $result = $this->dispatcher->dispatch(new ActionRequest(
            key: $tool->target,
            payload: $input,
            companyId: $context->companyId,
            actorId: $context->actorId,
            idempotencyKey: $idempotencyKey,
            confirmationId: $context->confirmationId,
            source: $context->source,
        ));

        return new AiToolResult(
            tool: $tool->name,
            target: $tool->target,
            kind: 'action',
            data: $result->data,
            latencyMs: (int) round((hrtime(true) - $started) / 1_000_000),
            auditId: $result->auditId,
            eventIds: $result->eventIds,
            replayed: $result->replayed,
        );
    }
}
