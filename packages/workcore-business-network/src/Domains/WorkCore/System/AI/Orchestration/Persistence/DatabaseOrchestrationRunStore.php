<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Persistence;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\OrchestrationRunStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\{AgentTurnRequest,ConversationRecord,OrchestrationRunRecord,ResolvedAgentProfile};
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Orchestration\OrchestrationStateMachine;
use App\Domains\WorkCore\System\AI\Security\SensitiveDataRedactor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class DatabaseOrchestrationRunStore implements OrchestrationRunStoreContract
{
    public function __construct(
        private ConnectionInterface $db,
        private SensitiveDataRedactor $redactor,
        private OrchestrationStateMachine $states,
    ) {}

    public function start(AgentTurnRequest $request, ConversationRecord $conversation, ResolvedAgentProfile $agent): OrchestrationRunRecord
    {
        $idempotencyKey = trim((string) $request->idempotencyKey);
        if ($idempotencyKey !== '') {
            $existing = $this->findByIdempotency($request->companyId, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
            $idempotencyKey = hash('sha256', $idempotencyKey);
        } else {
            $idempotencyKey = hash('sha256', (string) Str::ulid());
        }

        $conversationId = $this->db->table('tz_ai_conversations')
            ->where('company_id', $request->companyId)
            ->where('public_id', $conversation->publicId)
            ->value('id');
        if ($conversationId === null) {
            throw new AiOrchestrationException('Cannot start an AI run without a company-scoped conversation.', 409);
        }

        $publicId = (string) Str::ulid();
        $now = now();
        $snapshot = [
            'text_hash' => hash('sha256', trim($request->text)),
            'text_length' => strlen(trim($request->text)),
            'agent_public_id' => $agent->publicId,
            'agent_version_public_id' => $agent->versionPublicId,
            'metadata' => $this->redactor->redact($request->metadata),
        ];
        $this->db->table('tz_ai_orchestration_runs')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'conversation_id' => (int) $conversationId,
            'actor_user_id' => $request->actorId,
            'agent_public_id' => $agent->publicId,
            'status' => 'running',
            'idempotency_key' => $idempotencyKey,
            'source' => $request->source,
            'channel' => $request->channel,
            'step_count' => 0,
            'request_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new OrchestrationRunRecord(
            $publicId,
            $request->companyId,
            $request->actorId,
            $conversation->publicId,
            $agent->publicId,
            'running',
            0,
            null,
            ['idempotency_key' => $request->idempotencyKey, 'request_snapshot' => $snapshot],
        );
    }

    public function recordStep(string $runPublicId, string $type, string $status, array $input = [], array $output = []): void
    {
        $this->db->transaction(function () use ($runPublicId, $type, $status, $input, $output): void {
            $run = $this->db->table('tz_ai_orchestration_runs')->where('public_id', $runPublicId)->lockForUpdate()->first(['id','company_id','step_count']);
            if ($run === null) {
                throw new AiOrchestrationException('The AI orchestration run was not found.', 404);
            }
            $sequence = ((int) $run->step_count) + 1;
            $now = now();
            $this->db->table('tz_ai_orchestration_steps')->insert([
                'public_id' => (string) Str::ulid(),
                'company_id' => (int) $run->company_id,
                'orchestration_run_id' => (int) $run->id,
                'sequence' => $sequence,
                'step_type' => $type,
                'status' => $status,
                'input_snapshot' => $input === [] ? null : json_encode($this->redactor->redact($input), JSON_THROW_ON_ERROR),
                'output_snapshot' => $output === [] ? null : json_encode($this->redactor->redact($output), JSON_THROW_ON_ERROR),
                'started_at' => $now,
                'completed_at' => $status === 'pending' ? null : $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->table('tz_ai_orchestration_runs')->where('id', $run->id)->update(['step_count' => $sequence, 'updated_at' => $now]);
        }, 3);
    }

    public function transition(string $runPublicId, string $status, array $metadata = []): OrchestrationRunRecord
    {
        return $this->db->transaction(function () use ($runPublicId, $status, $metadata): OrchestrationRunRecord {
            $row = $this->db->table('tz_ai_orchestration_runs as r')
                ->join('tz_ai_conversations as c', function ($join): void {
                    $join->on('c.id', '=', 'r.conversation_id')->on('c.company_id', '=', 'r.company_id');
                })
                ->where('r.public_id', $runPublicId)
                ->lockForUpdate()
                ->first(['r.*','c.public_id as conversation_public_id']);
            if ($row === null) {
                throw new AiOrchestrationException('The AI orchestration run was not found.', 404);
            }

            $this->states->assertCanTransition((string) $row->status, $status);
            $update = [
                'status' => $status,
                'pending_approval_public_id' => array_key_exists('pending_approval_public_id', $metadata) ? $metadata['pending_approval_public_id'] : $row->pending_approval_public_id,
                'updated_at' => now(),
            ];
            if (isset($metadata['result']) && is_array($metadata['result'])) {
                $update['result_snapshot'] = json_encode($this->redactor->redact($metadata['result']), JSON_THROW_ON_ERROR);
            }
            if (isset($metadata['error_code'])) {
                $update['error_code'] = (string) $metadata['error_code'];
            }
            if (isset($metadata['error_message'])) {
                $update['error_message'] = (string) $metadata['error_message'];
            }
            if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
                $update['completed_at'] = now();
            }
            $this->db->table('tz_ai_orchestration_runs')->where('id', $row->id)->update($update);

            return $this->find((int) $row->company_id, $runPublicId)
                ?? throw new AiOrchestrationException('The AI orchestration run could not be reloaded.', 500);
        }, 3);
    }

    public function find(int $companyId, string $runPublicId): ?OrchestrationRunRecord
    {
        $row = $this->db->table('tz_ai_orchestration_runs as r')
            ->join('tz_ai_conversations as c', function ($join): void {
                $join->on('c.id', '=', 'r.conversation_id')->on('c.company_id', '=', 'r.company_id');
            })
            ->where('r.company_id', $companyId)
            ->where('r.public_id', $runPublicId)
            ->first(['r.*','c.public_id as conversation_public_id']);
        return $row === null ? null : $this->map($row);
    }

    public function findByIdempotency(int $companyId, string $idempotencyKey): ?OrchestrationRunRecord
    {
        $hash = hash('sha256', trim($idempotencyKey));
        $row = $this->db->table('tz_ai_orchestration_runs as r')
            ->join('tz_ai_conversations as c', function ($join): void {
                $join->on('c.id', '=', 'r.conversation_id')->on('c.company_id', '=', 'r.company_id');
            })
            ->where('r.company_id', $companyId)
            ->where('r.idempotency_key', $hash)
            ->first(['r.*','c.public_id as conversation_public_id']);
        return $row === null ? null : $this->map($row, $idempotencyKey);
    }

    private function map(object $row, ?string $originalIdempotency = null): OrchestrationRunRecord
    {
        $request = $this->decode($row->request_snapshot ?? null);
        $result = $this->decode($row->result_snapshot ?? null);
        return new OrchestrationRunRecord(
            (string) $row->public_id,
            (int) $row->company_id,
            (int) $row->actor_user_id,
            (string) $row->conversation_public_id,
            $row->agent_public_id === null ? null : (string) $row->agent_public_id,
            (string) $row->status,
            (int) $row->step_count,
            $row->pending_approval_public_id === null ? null : (string) $row->pending_approval_public_id,
            [
                'idempotency_key' => $originalIdempotency,
                'request_snapshot' => $request,
                'result' => $result,
                'error_code' => $row->error_code ?? null,
                'error_message' => $row->error_message ?? null,
            ],
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
