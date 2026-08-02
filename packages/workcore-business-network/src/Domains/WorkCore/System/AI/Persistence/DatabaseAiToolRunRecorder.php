<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Persistence;

use App\Domains\WorkCore\System\AI\Contracts\AiToolRunRecorderContract;
use App\Domains\WorkCore\System\AI\DTO\{AiToolExecutionContext,AiToolResult};
use App\Domains\WorkCore\System\AI\Security\SensitiveDataRedactor;
use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

final class DatabaseAiToolRunRecorder implements AiToolRunRecorderContract
{
    public function __construct(private ConnectionInterface $db, private SensitiveDataRedactor $redactor) {}

    public function start(AiToolDefinition $tool, array $input, AiToolExecutionContext $context): string
    {
        $publicId = (string) Str::ulid();
        $aiRunId = $context->aiRunPublicId === null ? null : $this->db->table('tz_ai_runs')->where('public_id', $context->aiRunPublicId)->value('id');
        $this->db->table('tz_ai_tool_runs')->insert([
            'public_id' => $publicId,
            'company_id' => $context->companyId,
            'ai_run_id' => $aiRunId,
            'actor_user_id' => $context->actorId,
            'tool_name' => $tool->name,
            'tool_kind' => $tool->kind,
            'target_key' => $tool->target,
            'status' => 'running',
            'risk' => $tool->risk,
            'confirmation_id' => $context->confirmationId,
            'idempotency_key' => $context->idempotencyKey,
            'input_snapshot' => json_encode($this->redactor->redact($input), JSON_THROW_ON_ERROR),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $publicId;
    }

    public function complete(string $toolRunPublicId, AiToolResult $result): void
    {
        $this->db->table('tz_ai_tool_runs')->where('public_id', $toolRunPublicId)->update([
            'status' => 'completed',
            'output_snapshot' => json_encode($this->redactor->redact($result->data), JSON_THROW_ON_ERROR),
            'action_audit_id' => $result->auditId,
            'event_ids' => json_encode($result->eventIds, JSON_THROW_ON_ERROR),
            'latency_ms' => $result->latencyMs,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function fail(string $toolRunPublicId, Throwable $exception): void
    {
        $this->db->table('tz_ai_tool_runs')->where('public_id', $toolRunPublicId)->update([
            'status' => 'failed',
            'error_code' => (string) $exception->getCode(),
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
