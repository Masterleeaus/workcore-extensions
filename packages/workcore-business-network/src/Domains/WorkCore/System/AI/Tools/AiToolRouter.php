<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

use App\Domains\WorkCore\System\AI\Contracts\{AiToolExecutorContract,AiToolRunRecorderContract};
use App\Domains\WorkCore\System\AI\DTO\{AiToolExecutionContext,AiToolResult};
use App\Domains\WorkCore\System\AI\Exceptions\AiToolException;
use App\Domains\WorkCore\System\AI\Security\NativeAiAccessGate;
use Throwable;

final class AiToolRouter implements AiToolExecutorContract
{
    public function __construct(
        private NativeToolCatalogue $catalogue,
        private WorkCoreActionToolExecutor $actions,
        private WorkCoreReadToolExecutor $reads,
        private AiToolRunRecorderContract $recorder,
        private NativeAiAccessGate $access,
    ) {}

    /** @param array<string,mixed> $input */
    public function execute(string $toolName, array $input, AiToolExecutionContext $context): AiToolResult
    {
        $this->access->assertToolAccess($context->companyId, $context->actorId);
        $tool = $this->catalogue->available($context->companyId, $context->actorId, $context->channel, $context->agent)[$toolName] ?? null;
        if ($tool === null) {
            throw new AiToolException("AI tool [{$toolName}] is unavailable in the active company, actor, agent or channel context.", 403);
        }

        $toolRunPublicId = $this->recorder->start($tool, $input, $context);
        try {
            $result = $tool->kind === 'action'
                ? $this->actions->execute($tool, $input, $context)
                : $this->reads->execute($tool, $input, $context);
            $this->recorder->complete($toolRunPublicId, $result);
            return $result;
        } catch (Throwable $exception) {
            $this->recorder->fail($toolRunPublicId, $exception);
            throw $exception;
        }
    }
}
