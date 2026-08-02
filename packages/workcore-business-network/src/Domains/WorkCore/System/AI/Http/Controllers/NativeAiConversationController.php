<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Http\Controllers;

use App\Domains\WorkCore\System\AI\Orchestration\AgentOrchestrator;
use App\Domains\WorkCore\System\AI\Orchestration\Contracts\ConversationStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\AgentTurnRequest;
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Security\NativeAiAccessGate;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use Illuminate\Http\{JsonResponse,Request};

final class NativeAiConversationController
{
    public function index(Request $request, ConversationStoreContract $store, NativeAiAccessGate $access, PermissionResolverContract $permissions, ApiResponseFactory $api): JsonResponse
    {
        [$companyId, $actorId] = $this->scope($access, $permissions);
        $items = array_map(static fn ($item): array => [
            'public_id' => $item->publicId,
            'channel' => $item->channel,
            'agent_public_id' => $item->agentPublicId,
            'status' => $item->status,
            'title' => $item->title,
        ], $store->listForActor($companyId, $actorId, $request->integer('limit', 50)));
        return $api->success($items);
    }

    public function show(string $conversation, Request $request, ConversationStoreContract $store, NativeAiAccessGate $access, PermissionResolverContract $permissions, ApiResponseFactory $api): JsonResponse
    {
        [$companyId, $actorId] = $this->scope($access, $permissions);
        $record = $store->find($companyId, $conversation)
            ?? throw new AiOrchestrationException('The AI conversation was not found.', 404);
        if ($record->actorId !== $actorId) {
            throw new AiOrchestrationException('The AI conversation belongs to another actor.', 403);
        }
        return $api->success([
            'conversation' => [
                'public_id' => $record->publicId,
                'channel' => $record->channel,
                'agent_public_id' => $record->agentPublicId,
                'status' => $record->status,
                'title' => $record->title,
            ],
            'messages' => array_map(static fn ($message): array => $message->toArray(), $store->history($companyId, $conversation, $request->integer('limit', 100))),
        ]);
    }

    public function turn(Request $request, AgentOrchestrator $orchestrator, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'conversation_public_id' => ['nullable', 'string', 'size:26'],
            'agent_public_id' => ['nullable', 'string', 'size:26'],
            'channel' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'metadata' => ['nullable', 'array'],
        ]);
        $tenant = workcore_tenant();
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotency === '', 422, 'Idempotency-Key header is required.');

        $result = $orchestrator->turn(new AgentTurnRequest(
            companyId: (int) $tenant->companyId(),
            actorId: (int) $tenant->userId(),
            text: (string) $validated['text'],
            conversationPublicId: $validated['conversation_public_id'] ?? null,
            agentPublicId: $validated['agent_public_id'] ?? null,
            channel: (string) ($validated['channel'] ?? 'internal'),
            source: 'workcore.native_ai.api',
            idempotencyKey: $idempotency,
            metadata: (array) ($validated['metadata'] ?? []),
        ));
        return $api->success($result->toArray(), status: $result->status === 'awaiting_approval' ? 202 : 200);
    }

    public function resume(string $approval, AgentOrchestrator $orchestrator, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $result = $orchestrator->resume((int) $tenant->companyId(), (int) $tenant->userId(), $approval);
        return $api->success($result->toArray());
    }

    /** @return array{0:int,1:int} */
    private function scope(NativeAiAccessGate $access, PermissionResolverContract $permissions): array
    {
        $tenant = workcore_tenant();
        $companyId = (int) $tenant->companyId();
        $actorId = (int) $tenant->userId();
        $access->assertModelAccess($companyId, $actorId);
        if (! $permissions->allows($actorId, $companyId, 'workcore.ai.view_conversations')) {
            throw new AiOrchestrationException('The actor is not permitted to view WorkCore AI conversations.', 403);
        }
        return [$companyId, $actorId];
    }
}
