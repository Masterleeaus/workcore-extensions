<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Persistence;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\ConversationStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\{AgentTurnRequest,ConversationMessage,ConversationRecord,ResolvedAgentProfile};
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Security\SensitiveDataRedactor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class DatabaseConversationStore implements ConversationStoreContract
{
    public function __construct(private ConnectionInterface $db, private SensitiveDataRedactor $redactor) {}

    public function findOrCreate(AgentTurnRequest $request, ResolvedAgentProfile $agent): ConversationRecord
    {
        if ($request->conversationPublicId !== null && trim($request->conversationPublicId) !== '') {
            return $this->find($request->companyId, $request->conversationPublicId)
                ?? throw new AiOrchestrationException('The requested AI conversation was not found in the active company.', 404);
        }

        $publicId = (string) Str::ulid();
        $now = now();
        $this->db->table('tz_ai_conversations')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'actor_user_id' => $request->actorId,
            'agent_public_id' => $agent->publicId,
            'channel' => $request->channel,
            'title' => Str::limit(trim($request->text), 120, ''),
            'status' => 'active',
            'metadata' => json_encode($this->redactor->redact($request->metadata), JSON_THROW_ON_ERROR),
            'last_message_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new ConversationRecord($publicId, $request->companyId, $request->actorId, $request->channel, $agent->publicId, 'active', Str::limit(trim($request->text), 120, ''));
    }

    public function append(
        int $companyId,
        string $conversationPublicId,
        string $role,
        string $content,
        ?string $name = null,
        ?string $toolCallId = null,
        array $toolCalls = [],
        array $metadata = [],
    ): ConversationMessage {
        return $this->db->transaction(function () use ($companyId, $conversationPublicId, $role, $content, $name, $toolCallId, $toolCalls, $metadata): ConversationMessage {
            $conversation = $this->db->table('tz_ai_conversations')
                ->where('company_id', $companyId)
                ->where('public_id', $conversationPublicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id']);
            if ($conversation === null) {
                throw new AiOrchestrationException('The AI conversation was not found in the active company.', 404);
            }

            $sequence = ((int) $this->db->table('tz_ai_messages')->where('conversation_id', $conversation->id)->max('sequence')) + 1;
            $publicId = (string) Str::ulid();
            $now = now();
            $this->db->table('tz_ai_messages')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'conversation_id' => (int) $conversation->id,
                'sequence' => $sequence,
                'role' => $role,
                'content' => $content,
                'name' => $name,
                'tool_call_id' => $toolCallId,
                'tool_calls' => $toolCalls === [] ? null : json_encode($toolCalls, JSON_THROW_ON_ERROR),
                'metadata' => $metadata === [] ? null : json_encode($this->redactor->redact($metadata), JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->table('tz_ai_conversations')->where('id', $conversation->id)->update([
                'last_message_at' => $now,
                'updated_at' => $now,
            ]);

            return new ConversationMessage($publicId, $sequence, $role, $content, $name, $toolCallId, $toolCalls, $this->redactor->redact($metadata));
        }, 3);
    }

    public function history(int $companyId, string $conversationPublicId, int $limit): array
    {
        $conversation = $this->db->table('tz_ai_conversations')
            ->where('company_id', $companyId)
            ->where('public_id', $conversationPublicId)
            ->whereNull('deleted_at')
            ->first(['id']);
        if ($conversation === null) {
            return [];
        }

        $rows = $this->db->table('tz_ai_messages')
            ->where('company_id', $companyId)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('sequence')
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->reverse()
            ->values();

        return array_map(fn ($row): ConversationMessage => $this->mapMessage($row), $rows->all());
    }

    public function find(int $companyId, string $conversationPublicId): ?ConversationRecord
    {
        $row = $this->db->table('tz_ai_conversations')
            ->where('company_id', $companyId)
            ->where('public_id', $conversationPublicId)
            ->whereNull('deleted_at')
            ->first();
        return $row === null ? null : $this->mapConversation($row);
    }

    public function listForActor(int $companyId, int $actorId, int $limit = 50): array
    {
        $rows = $this->db->table('tz_ai_conversations')
            ->where('company_id', $companyId)
            ->where('actor_user_id', $actorId)
            ->whereNull('deleted_at')
            ->orderByDesc('last_message_at')
            ->limit(max(1, min(100, $limit)))
            ->get();

        return array_map(fn ($row): ConversationRecord => $this->mapConversation($row), $rows->all());
    }

    private function mapConversation(object $row): ConversationRecord
    {
        return new ConversationRecord(
            (string) $row->public_id,
            (int) $row->company_id,
            (int) $row->actor_user_id,
            (string) $row->channel,
            $row->agent_public_id === null ? null : (string) $row->agent_public_id,
            (string) $row->status,
            $row->title === null ? null : (string) $row->title,
        );
    }

    private function mapMessage(object $row): ConversationMessage
    {
        return new ConversationMessage(
            (string) $row->public_id,
            (int) $row->sequence,
            (string) $row->role,
            (string) $row->content,
            $row->name === null ? null : (string) $row->name,
            $row->tool_call_id === null ? null : (string) $row->tool_call_id,
            $this->decode($row->tool_calls ?? null),
            $this->decode($row->metadata ?? null),
        );
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }
}
