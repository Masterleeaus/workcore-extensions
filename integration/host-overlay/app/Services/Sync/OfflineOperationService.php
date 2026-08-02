<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

final class OfflineOperationService
{
    private const TABLE = 'workcore_sync_operations';

    public function __construct(
        private ConnectionInterface $db,
        private BusinessActionDispatcher $dispatcher,
    ) {}

    /** @param array<string,mixed> $operation
     *  @return array{data:array<string,mixed>,replayed:bool,status:string}
     */
    public function execute(int $companyId, int $actorId, array $operation): array
    {
        $payload = (array) ($operation['payload'] ?? []);
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $payloadHash = hash('sha256', $payloadJson);
        $operationId = (string) $operation['operation_id'];

        $record = $this->claim(
            companyId: $companyId,
            actorId: $actorId,
            operationId: $operationId,
            deviceId: (string) $operation['device_id'],
            action: (string) $operation['action'],
            payloadJson: $payloadJson,
            payloadHash: $payloadHash,
            occurredAt: Carbon::parse((string) $operation['occurred_at']),
        );

        if ($record->status === 'completed') {
            return [
                'data' => (array) json_decode((string) ($record->result_json ?: '{}'), true, 512, JSON_THROW_ON_ERROR),
                'replayed' => true,
                'status' => 'completed',
            ];
        }

        try {
            $result = $this->dispatcher->dispatch(new ActionRequest(
                key: (string) $operation['action'],
                payload: $payload,
                companyId: $companyId,
                actorId: $actorId,
                idempotencyKey: 'offline:' . $operationId,
                confirmationId: isset($operation['confirmation_id']) ? (string) $operation['confirmation_id'] : null,
                source: 'workcore_offline_sync',
            ));

            $resultArray = $result->toArray();
            $this->db->table(self::TABLE)
                ->where('company_id', $companyId)
                ->where('operation_id', $operationId)
                ->update([
                    'status' => 'completed',
                    'result_json' => json_encode($resultArray, JSON_THROW_ON_ERROR),
                    'error_message' => null,
                    'completed_at' => now(),
                    'locked_at' => null,
                    'updated_at' => now(),
                ]);

            return ['data' => $resultArray, 'replayed' => false, 'status' => 'completed'];
        } catch (Throwable $exception) {
            $this->db->table(self::TABLE)
                ->where('company_id', $companyId)
                ->where('operation_id', $operationId)
                ->update([
                    'status' => 'failed',
                    'attempts' => $this->db->raw('attempts + 1'),
                    'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                    'locked_at' => null,
                    'available_at' => now()->addSeconds((int) config('workcore.sync.retry_seconds', 60)),
                    'updated_at' => now(),
                ]);
            throw $exception;
        }
    }

    private function claim(
        int $companyId,
        int $actorId,
        string $operationId,
        string $deviceId,
        string $action,
        string $payloadJson,
        string $payloadHash,
        Carbon $occurredAt,
    ): object {
        return $this->db->transaction(function () use ($companyId, $actorId, $operationId, $deviceId, $action, $payloadJson, $payloadHash, $occurredAt): object {
            $record = $this->db->table(self::TABLE)
                ->where('company_id', $companyId)
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();

            if ($record !== null) {
                if (! hash_equals((string) $record->payload_sha256, $payloadHash) || (string) $record->action_key !== $action) {
                    throw new RuntimeException('Offline operation ID was reused with different action data.');
                }

                if ($record->status === 'completed') {
                    return $record;
                }

                $staleAfter = max(30, (int) config('workcore.sync.processing_timeout_seconds', 300));
                if ($record->status === 'processing' && $record->locked_at !== null && Carbon::parse($record->locked_at)->gt(now()->subSeconds($staleAfter))) {
                    throw new RuntimeException('Offline operation is already processing.');
                }

                if ($record->available_at !== null && Carbon::parse($record->available_at)->isFuture()) {
                    throw new RuntimeException('Offline operation is waiting for its retry window.');
                }

                $this->db->table(self::TABLE)->where('id', $record->id)->update([
                    'status' => 'processing',
                    'locked_at' => now(),
                    'updated_at' => now(),
                ]);

                return $this->db->table(self::TABLE)->where('id', $record->id)->first();
            }

            $id = $this->db->table(self::TABLE)->insertGetId([
                'company_id' => $companyId,
                'operation_id' => $operationId,
                'actor_id' => $actorId,
                'device_id' => $deviceId,
                'action_key' => $action,
                'payload_json' => $payloadJson,
                'payload_sha256' => $payloadHash,
                'status' => 'processing',
                'attempts' => 0,
                'occurred_at' => $occurredAt,
                'available_at' => now(),
                'locked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->db->table(self::TABLE)->where('id', $id)->first();
        }, 3);
    }
}
