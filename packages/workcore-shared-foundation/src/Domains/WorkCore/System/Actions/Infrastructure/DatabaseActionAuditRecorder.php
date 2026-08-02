<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Infrastructure;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\ActionAuditRecorderContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class DatabaseActionAuditRecorder implements ActionAuditRecorderContract
{
    private const TABLE = 'tz_action_executions';

    public function __construct(private ConnectionInterface $db) {}

    public function recordCompleted(ActionDefinition $definition, ActionRequest $request, ActionHandlerResult $result, string $correlationId): string
    {
        return $this->insert($definition, $request, $correlationId, 'completed', $result->data, null, $result->aggregate?->type, $result->aggregate?->id);
    }

    public function recordFailed(ActionDefinition $definition, ActionRequest $request, string $correlationId, string $message): string
    {
        return $this->insert($definition, $request, $correlationId, 'failed', null, $message, null, null);
    }

    /** @param array<string,mixed>|null $result */
    private function insert(ActionDefinition $definition, ActionRequest $request, string $correlationId, string $status, ?array $result, ?string $error, ?string $aggregateType, ?string $aggregatePublicId): string
    {
        $publicId = (string) Str::ulid();
        $this->db->table(self::TABLE)->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'actor_user_id' => $request->actorId,
            'action_key' => $request->key,
            'risk_level' => $definition->risk,
            'source' => $request->source,
            'confirmation_id' => $request->confirmationId,
            'idempotency_key' => $request->idempotencyKey,
            'correlation_id' => $correlationId,
            'aggregate_type' => $aggregateType,
            'aggregate_public_id' => $aggregatePublicId,
            'status' => $status,
            'request_json' => json_encode($request->payload, JSON_THROW_ON_ERROR),
            'result_json' => $result === null ? null : json_encode($result, JSON_THROW_ON_ERROR),
            'error_message' => $error === null ? null : mb_substr($error, 0, 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }
}
