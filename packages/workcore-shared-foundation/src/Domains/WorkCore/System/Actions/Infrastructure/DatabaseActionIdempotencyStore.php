<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Infrastructure;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\Contracts\ActionIdempotencyStoreContract;
use App\Domains\WorkCore\System\Actions\Exceptions\ActionConflictException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

final class DatabaseActionIdempotencyStore implements ActionIdempotencyStoreContract
{
    private const TABLE = 'tz_action_idempotency';

    public function __construct(private ConnectionInterface $db) {}

    public function replay(ActionRequest $request): ?ActionResult
    {
        $row = $this->db->table(self::TABLE)
            ->where('company_id', $request->companyId)
            ->where('action_key', $request->key)
            ->where('idempotency_key', $request->idempotencyKey)
            ->first();

        if ($row === null) {
            return null;
        }
        if ($row->payload_hash !== $request->payloadHash()) {
            throw new ActionConflictException('The idempotency key was already used with different input.');
        }
        if ($row->status !== 'completed' || ! is_string($row->result_json)) {
            throw new ActionConflictException('The action is already in progress or previously failed.');
        }

        $stored = json_decode($row->result_json, true, 512, JSON_THROW_ON_ERROR);

        return new ActionResult(
            actionKey: (string) $stored['action'],
            data: (array) $stored['data'],
            correlationId: (string) $stored['correlation_id'],
            auditId: (string) $stored['audit_id'],
            eventIds: (array) ($stored['event_ids'] ?? []),
            replayed: true,
        );
    }

    public function reserve(ActionRequest $request): void
    {
        try {
            $this->db->table(self::TABLE)->insert([
                'company_id' => $request->companyId,
                'action_key' => $request->key,
                'idempotency_key' => $request->idempotencyKey,
                'payload_hash' => $request->payloadHash(),
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            throw new ActionConflictException('The action idempotency key is already reserved.', previous: $exception);
        }
    }

    public function complete(ActionRequest $request, ActionResult $result): void
    {
        $this->db->table(self::TABLE)
            ->where('company_id', $request->companyId)
            ->where('action_key', $request->key)
            ->where('idempotency_key', $request->idempotencyKey)
            ->update([
                'status' => 'completed',
                'result_json' => json_encode($result->toArray(), JSON_THROW_ON_ERROR),
                'error_message' => null,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function fail(ActionRequest $request, string $message): void
    {
        $this->db->table(self::TABLE)->updateOrInsert(
            [
                'company_id' => $request->companyId,
                'action_key' => $request->key,
                'idempotency_key' => $request->idempotencyKey,
            ],
            [
                'payload_hash' => $request->payloadHash(),
                'status' => 'failed',
                'result_json' => null,
                'error_message' => mb_substr($message, 0, 1000),
                'completed_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
