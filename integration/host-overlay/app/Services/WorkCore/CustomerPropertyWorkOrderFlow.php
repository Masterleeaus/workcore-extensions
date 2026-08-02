<?php

declare(strict_types=1);

namespace App\Services\WorkCore;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CustomerPropertyWorkOrderFlow
{
    public function __construct(
        private BusinessActionDispatcher $dispatcher,
        private ConnectionInterface $db,
    ) {}

    /** @param array<string,mixed> $payload */
    public function execute(array $payload, int $companyId, int $actorId, string $idempotencyKey, string $confirmationId): array
    {
        $existing = $this->db->table('workcore_business_flows')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing && $existing->status === 'completed') {
            return json_decode((string) $existing->result, true, 512, JSON_THROW_ON_ERROR);
        }

        $flowId = $existing?->public_id ?? (string) Str::ulid();
        if (! $existing) {
            $this->db->table('workcore_business_flows')->insert([
                'public_id' => $flowId,
                'company_id' => $companyId,
                'actor_id' => $actorId,
                'flow_type' => 'customer_property_work_order',
                'idempotency_key' => $idempotencyKey,
                'status' => 'running',
                'request' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->db->table('workcore_business_flows')->where('id', $existing->id)->update([
                'status' => 'running', 'error' => null, 'updated_at' => now(),
            ]);
        }

        try {
            $customer = $this->dispatcher->dispatch(new ActionRequest(
                key: 'workcore.customer.create',
                payload: (array) ($payload['customer'] ?? []),
                companyId: $companyId,
                actorId: $actorId,
                idempotencyKey: $idempotencyKey . ':customer',
                confirmationId: $confirmationId,
                source: 'workcore_business_flow',
            ))->data;

            $premisesPayload = (array) ($payload['property'] ?? $payload['premises'] ?? []);
            $premisesPayload['customer_public_id'] = $customer['public_id'] ?? throw new RuntimeException('Customer action did not return a public ID.');
            $premises = $this->dispatcher->dispatch(new ActionRequest(
                key: 'workcore.premises.create',
                payload: $premisesPayload,
                companyId: $companyId,
                actorId: $actorId,
                idempotencyKey: $idempotencyKey . ':premises',
                confirmationId: $confirmationId,
                source: 'workcore_business_flow',
            ))->data;

            $workOrderPayload = (array) ($payload['work_order'] ?? []);
            $workOrderPayload['customer_public_id'] = $customer['public_id'];
            $workOrderPayload['premises_public_id'] = $premises['public_id'] ?? throw new RuntimeException('Premises action did not return a public ID.');
            $workOrder = $this->dispatcher->dispatch(new ActionRequest(
                key: 'workcore.work_order.create',
                payload: $workOrderPayload,
                companyId: $companyId,
                actorId: $actorId,
                idempotencyKey: $idempotencyKey . ':work-order',
                confirmationId: $confirmationId,
                source: 'workcore_business_flow',
            ))->data;

            $result = [
                'flow_id' => $flowId,
                'status' => 'completed',
                'customer' => $customer,
                'property' => $premises,
                'work_order' => $workOrder,
            ];

            $this->db->table('workcore_business_flows')->where('public_id', $flowId)->update([
                'status' => 'completed',
                'result' => json_encode($result, JSON_THROW_ON_ERROR),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $this->db->table('workcore_business_flows')->where('public_id', $flowId)->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);
            throw $exception;
        }
    }
}
