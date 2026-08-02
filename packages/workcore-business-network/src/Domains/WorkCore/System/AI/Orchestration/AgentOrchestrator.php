<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Contracts\{AiToolExecutorContract,ModelGatewayContract,ToolCatalogueContract};
use App\Domains\WorkCore\System\AI\DTO\{AiToolExecutionContext,ModelMessage,ModelRequest};
use App\Domains\WorkCore\System\AI\Orchestration\Contracts\{AgentProfileResolverContract,ApprovalStoreContract,ConversationStoreContract,MemoryStoreContract,OrchestrationRunStoreContract};
use App\Domains\WorkCore\System\AI\Orchestration\DTO\{AgentTurnRequest,AgentTurnResult,ApprovalRecord,ConversationRecord,OrchestrationRunRecord,ResolvedAgentProfile,ToolCall};
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Security\NativeAiAccessGate;
use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;
use Throwable;

final class AgentOrchestrator
{
    public function __construct(
        private NativeAiAccessGate $access,
        private AgentProfileResolverContract $agents,
        private ConversationStoreContract $conversations,
        private OrchestrationRunStoreContract $runs,
        private ApprovalStoreContract $approvals,
        private MemoryStoreContract $memories,
        private ModelGatewayContract $models,
        private ToolCatalogueContract $catalogue,
        private AiToolExecutorContract $tools,
        private ContextWindowBuilder $context,
        private ToolCallNormalizer $normalizer,
        private ToolApprovalPolicy $approvalPolicy,
        private OrchestrationStateMachine $states,
        private int $maxSteps = 8,
        private int $maxToolCalls = 12,
        private int $approvalExpiryMinutes = 30,
        private int $memoryLimit = 20,
    ) {
        $this->maxSteps = max(1, min(32, $this->maxSteps));
        $this->maxToolCalls = max(1, min(64, $this->maxToolCalls));
        $this->approvalExpiryMinutes = max(1, min(1440, $this->approvalExpiryMinutes));
        $this->memoryLimit = max(0, min(100, $this->memoryLimit));
    }

    public function turn(AgentTurnRequest $request): AgentTurnResult
    {
        $this->access->assertModelAccess($request->companyId, $request->actorId);
        if ($request->idempotencyKey !== null && trim($request->idempotencyKey) !== '') {
            $existing = $this->runs->findByIdempotency($request->companyId, $request->idempotencyKey);
            if ($existing !== null) {
                return $this->replayResult($existing);
            }
        }

        $agent = $this->agents->resolve($request->companyId, $request->agentPublicId);
        $conversation = $this->conversations->findOrCreate($request, $agent);

        if ($conversation->companyId !== $request->companyId || $conversation->actorId !== $request->actorId) {
            throw new AiOrchestrationException('The requested AI conversation is outside the active company or actor scope.', 403);
        }

        $this->conversations->append(
            $request->companyId,
            $conversation->publicId,
            'user',
            trim($request->text),
            metadata: ['source' => $request->source] + $request->metadata,
        );

        $run = $this->runs->start($request, $conversation, $agent);

        try {
            return $this->continueRun($run, $conversation, $agent, $request->channel, $request->source);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
            throw $exception;
        }
    }

    public function resume(int $companyId, int $actorId, string $approvalPublicId): AgentTurnResult
    {
        $this->access->assertToolAccess($companyId, $actorId);
        $approval = $this->approvals->find($companyId, $approvalPublicId)
            ?? throw new AiOrchestrationException('The AI approval request was not found.', 404);

        $this->assertApprovalExecutable($approval);

        $run = $this->runs->find($companyId, $approval->runPublicId)
            ?? throw new AiOrchestrationException('The paused AI orchestration run was not found.', 404);
        if ($run->status !== 'awaiting_approval' || $run->pendingApprovalPublicId !== $approval->publicId) {
            throw new AiOrchestrationException('The AI orchestration run is not paused for this approval.', 409);
        }

        $conversation = $this->conversations->find($companyId, $approval->conversationPublicId)
            ?? throw new AiOrchestrationException('The AI conversation for this approval was not found.', 404);
        if ($conversation->actorId !== $run->actorId) {
            throw new AiOrchestrationException('The approval conversation actor does not match the paused run.', 409);
        }

        $agent = $this->agents->resolve($companyId, $run->agentPublicId);
        $available = $this->availableTools($companyId, $actorId, $conversation->channel, $agent);
        $tool = $available[$approval->toolName] ?? null;
        if (! $tool instanceof AiToolDefinition) {
            throw new AiOrchestrationException('The approved tool is no longer available to the active actor.', 403);
        }

        $decision = $this->approvalPolicy->decide($tool, $approval->publicId);
        if ($decision->action !== 'execute') {
            throw new AiOrchestrationException($decision->reason, 403);
        }

        $this->states->assertCanTransition($run->status, 'running');
        $run = $this->runs->transition($run->publicId, 'running', ['pending_approval_public_id' => null]);

        try {
            $result = $this->tools->execute(
                $tool->name,
                $approval->toolInput,
                new AiToolExecutionContext(
                    companyId: $companyId,
                    actorId: $actorId,
                    channel: $conversation->channel,
                    agent: $agent->key,
                    confirmationId: $approval->publicId,
                    idempotencyKey: hash('sha256', implode('|', [$run->publicId, $approval->toolCallId, $approval->publicId])),
                    source: 'workcore.native_ai.resume',
                ),
            );
            $this->approvals->markConsumed($companyId, $approval->publicId);
            $this->conversations->append(
                $companyId,
                $conversation->publicId,
                'tool',
                json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $tool->name,
                $approval->toolCallId,
                metadata: ['approval_public_id' => $approval->publicId],
            );
            $this->runs->recordStep(
                $run->publicId,
                'tool',
                'completed',
                ['tool_call_id' => $approval->toolCallId, 'tool_name' => $tool->name, 'approval_public_id' => $approval->publicId],
                $result->toArray(),
            );

            return $this->continueRun($run, $conversation, $agent, $conversation->channel, 'workcore.native_ai.resume', [$result->toArray()]);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
            throw $exception;
        }
    }

    /**
     * @param list<array<string,mixed>> $toolResults
     */
    private function continueRun(
        OrchestrationRunRecord $run,
        ConversationRecord $conversation,
        ResolvedAgentProfile $agent,
        string $channel,
        string $source,
        array $toolResults = [],
    ): AgentTurnResult {
        $toolCallCount = 0;

        for ($iteration = 0; $iteration < $this->maxSteps; $iteration++) {
            $history = $this->conversations->history($run->companyId, $conversation->publicId, max(20, $this->maxSteps * 6));
            $memory = $this->memories->recall($run->companyId, $run->actorId, $agent->publicId, $this->memoryLimit);
            $built = $this->context->build($agent, $memory, $history);
            $available = $this->availableTools($run->companyId, $run->actorId, $channel, $agent);
            $modelTools = array_values(array_map(static fn (AiToolDefinition $tool): array => $tool->toModelTool(), $available));

            $response = $this->models->generate(new ModelRequest(
                companyId: $run->companyId,
                actorId: $run->actorId,
                messages: $built['messages'],
                purpose: 'agent',
                model: $agent->model,
                system: $built['system'],
                tools: $modelTools,
                temperature: (float) ($agent->settings['temperature'] ?? 0.2),
                maxOutputTokens: (int) ($agent->settings['max_output_tokens'] ?? 2048),
                threadPublicId: $conversation->publicId,
                agentPublicId: $agent->publicId,
                metadata: [
                    'source' => $source,
                    'channel' => $channel,
                    'orchestration_run_public_id' => $run->publicId,
                    'iteration' => $iteration + 1,
                ],
            ));

            $this->runs->recordStep(
                $run->publicId,
                'model',
                'completed',
                ['iteration' => $iteration + 1, 'tool_count' => count($modelTools)],
                [
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'content_length' => $response->content === null ? 0 : strlen($response->content),
                    'tool_call_count' => count($response->toolCalls),
                    'usage' => $response->usage->toArray(),
                ],
            );

            if ($response->toolCalls === []) {
                $content = trim((string) $response->content);
                if ($content === '') {
                    throw new AiOrchestrationException('The model returned neither content nor a tool call.', 502);
                }

                $message = $this->conversations->append(
                    $run->companyId,
                    $conversation->publicId,
                    'assistant',
                    $content,
                    metadata: [
                        'provider' => $response->provider,
                        'model' => $response->model,
                        'provider_response_id' => $response->providerResponseId,
                    ],
                );
                $this->states->assertCanTransition($run->status, 'completed');
                $this->runs->transition($run->publicId, 'completed', ['completed_message_public_id' => $message->publicId, 'result' => ['status' => 'completed', 'content' => $content, 'message_public_id' => $message->publicId, 'tool_results' => $toolResults]]);

                return new AgentTurnResult(
                    'completed',
                    $conversation->publicId,
                    $run->publicId,
                    $content,
                    $message->publicId,
                    toolResults: $toolResults,
                    metadata: ['agent_key' => $agent->key],
                );
            }

            $this->conversations->append(
                $run->companyId,
                $conversation->publicId,
                'assistant',
                (string) ($response->content ?? ''),
                toolCalls: $response->toolCalls,
                metadata: ['provider' => $response->provider, 'model' => $response->model],
            );

            foreach ($response->toolCalls as $rawCall) {
                $toolCallCount++;
                if ($toolCallCount > $this->maxToolCalls) {
                    throw new AiOrchestrationException('The AI orchestration tool-call limit was exceeded.', 429);
                }

                try {
                    $call = $this->normalizer->normalize($rawCall);
                } catch (Throwable $exception) {
                    $this->appendToolError($run, $conversation, (string) ($rawCall['id'] ?? 'invalid'), 'invalid_tool_call', $exception->getMessage());
                    continue;
                }

                $tool = $available[$call->name] ?? null;
                if (! $tool instanceof AiToolDefinition) {
                    $this->appendToolError($run, $conversation, $call->id, 'unavailable_tool', "Tool [{$call->name}] is unavailable.");
                    continue;
                }

                $decision = $this->approvalPolicy->decide($tool);
                if ($decision->action === 'block') {
                    $blockedMessage = $this->conversations->append(
                        $run->companyId,
                        $conversation->publicId,
                        'assistant',
                        $decision->reason,
                        metadata: ['blocked_tool' => $tool->name, 'risk' => $tool->risk],
                    );
                    $this->states->assertCanTransition($run->status, 'failed');
                    $this->runs->transition($run->publicId, 'failed', ['blocked' => true, 'blocked_tool' => $tool->name, 'result' => ['status' => 'blocked', 'content' => $decision->reason, 'message_public_id' => $blockedMessage->publicId, 'tool_results' => $toolResults]]);

                    return new AgentTurnResult(
                        'blocked',
                        $conversation->publicId,
                        $run->publicId,
                        $decision->reason,
                        $blockedMessage->publicId,
                        toolResults: $toolResults,
                        metadata: ['blocked_tool' => $tool->name, 'risk' => $tool->risk],
                    );
                }

                if ($decision->action === 'pause') {
                    $approval = $this->approvals->create($run, $call, $tool, $this->approvalExpiryMinutes);
                    $this->runs->recordStep(
                        $run->publicId,
                        'approval',
                        'pending',
                        ['tool_call_id' => $call->id, 'tool_name' => $call->name, 'risk' => $tool->risk],
                        ['approval_public_id' => $approval->publicId],
                    );
                    $this->states->assertCanTransition($run->status, 'awaiting_approval');
                    $this->runs->transition($run->publicId, 'awaiting_approval', ['pending_approval_public_id' => $approval->publicId, 'result' => ['status' => 'awaiting_approval', 'content' => $response->content, 'approval_public_id' => $approval->publicId, 'tool_results' => $toolResults]]);

                    return new AgentTurnResult(
                        'awaiting_approval',
                        $conversation->publicId,
                        $run->publicId,
                        $response->content,
                        approvalPublicId: $approval->publicId,
                        toolResults: $toolResults,
                        metadata: [
                            'tool_name' => $tool->name,
                            'risk' => $tool->risk,
                            'reason' => $decision->reason,
                            'expires_at_epoch' => $approval->expiresAtEpoch,
                        ],
                    );
                }

                $result = $this->tools->execute(
                    $tool->name,
                    $call->arguments,
                    new AiToolExecutionContext(
                        companyId: $run->companyId,
                        actorId: $run->actorId,
                        channel: $channel,
                        agent: $agent->key,
                        idempotencyKey: hash('sha256', implode('|', [$run->publicId, $call->id, $tool->name])),
                        source: $source,
                    ),
                );
                $resultArray = $result->toArray();
                $toolResults[] = $resultArray;
                $this->conversations->append(
                    $run->companyId,
                    $conversation->publicId,
                    'tool',
                    json_encode($resultArray, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $tool->name,
                    $call->id,
                );
                $this->runs->recordStep(
                    $run->publicId,
                    'tool',
                    'completed',
                    ['tool_call_id' => $call->id, 'tool_name' => $tool->name],
                    $resultArray,
                );
            }
        }

        throw new AiOrchestrationException('The AI orchestration step limit was exceeded.', 429);
    }

    /** @return array<string,AiToolDefinition> */
    private function availableTools(int $companyId, int $actorId, string $channel, ResolvedAgentProfile $agent): array
    {
        $available = $this->catalogue->available($companyId, $actorId, $channel, $agent->key);
        $allowed = array_filter(
            $available,
            static fn (AiToolDefinition $tool): bool => $agent->allowsTool($tool->name),
        );
        ksort($allowed);

        return $allowed;
    }

    private function appendToolError(
        OrchestrationRunRecord $run,
        ConversationRecord $conversation,
        string $toolCallId,
        string $code,
        string $message,
    ): void {
        $output = ['ok' => false, 'error' => ['code' => $code, 'message' => $message]];
        $this->conversations->append(
            $run->companyId,
            $conversation->publicId,
            'tool',
            json_encode($output, JSON_THROW_ON_ERROR),
            'workcore_tool_error',
            $toolCallId,
        );
        $this->runs->recordStep($run->publicId, 'tool', 'failed', ['tool_call_id' => $toolCallId], $output);
    }

    private function assertApprovalExecutable(ApprovalRecord $approval): void
    {
        if ($approval->status !== 'approved') {
            throw new AiOrchestrationException('The AI approval request has not been approved.', 409);
        }
        if ($approval->consumed) {
            throw new AiOrchestrationException('The AI approval request has already been consumed.', 409);
        }
        if ($approval->isExpired()) {
            throw new AiOrchestrationException('The AI approval request has expired.', 410);
        }
    }

    private function replayResult(OrchestrationRunRecord $run): AgentTurnResult
    {
        $result = is_array($run->metadata['result'] ?? null) ? $run->metadata['result'] : [];
        $status = (string) ($result['status'] ?? match ($run->status) {
            'completed' => 'completed',
            'awaiting_approval' => 'awaiting_approval',
            'failed' => 'failed',
            default => 'failed',
        });

        if (! in_array($status, ['completed', 'awaiting_approval', 'blocked', 'failed'], true)) {
            $status = 'failed';
        }

        return new AgentTurnResult(
            $status,
            $run->conversationPublicId,
            $run->publicId,
            isset($result['content']) ? (string) $result['content'] : null,
            isset($result['message_public_id']) ? (string) $result['message_public_id'] : null,
            isset($result['approval_public_id']) ? (string) $result['approval_public_id'] : $run->pendingApprovalPublicId,
            is_array($result['tool_results'] ?? null) ? $result['tool_results'] : [],
            ['replayed' => true],
        );
    }

    private function failRun(OrchestrationRunRecord $run, Throwable $exception): void
    {
        if (! in_array($run->status, ['completed', 'failed', 'cancelled'], true)) {
            try {
                $this->states->assertCanTransition($run->status, 'failed');
                $this->runs->transition($run->publicId, 'failed', [
                    'error_code' => (string) $exception->getCode(),
                    'error_message' => substr($exception->getMessage(), 0, 2000),
                ]);
            } catch (Throwable) {
                // Preserve the original failure.
            }
        }
    }
}
