<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Rewind\BranchExecution;

use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use Illuminate\Database\ConnectionInterface;

final class WorkCoreBranchMutationTranslator
{
    public function __construct(
        private readonly BusinessActionRegistry $actions,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param array<string,mixed> $patch
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $currentState
     * @return list<array<string,mixed>>
     */
    public function translate(
        int $companyId,
        string $resourceType,
        string $entityId,
        array $patch,
        array $definition,
        array $currentState,
    ): array {
        $remaining = $patch;
        $operations = [];
        $reason = 'Approved Titan Rewind temporal branch execution.';

        if ($resourceType === 'work_order' && array_key_exists('status', $remaining)) {
            $operations[] = $this->businessAction('workcore.work_order.change_status', [
                'work_order_public_id' => $entityId,
                'status' => $remaining['status'],
                'reason' => $reason,
            ], ['status']);
            unset($remaining['status']);
        }

        if ($resourceType === 'appointment') {
            $scheduleFields = array_intersect_key($remaining, array_flip(['starts_at', 'ends_at', 'timezone']));
            if ($scheduleFields !== []) {
                $startsAt = $scheduleFields['starts_at'] ?? ($currentState['starts_at'] ?? null);
                $endsAt = $scheduleFields['ends_at'] ?? ($currentState['ends_at'] ?? null);
                if ($startsAt === null || $endsAt === null) {
                    throw new \RuntimeException('Appointment branch execution requires both starts_at and ends_at.');
                }
                $operations[] = $this->businessAction('workcore.appointment.reschedule', [
                    'appointment_public_id' => $entityId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'timezone' => $scheduleFields['timezone'] ?? ($currentState['timezone'] ?? null),
                    'reason' => $reason,
                ], array_keys($scheduleFields));
                foreach (array_keys($scheduleFields) as $field) unset($remaining[$field]);
            }
            if (array_key_exists('status', $remaining)) {
                $operations[] = $this->businessAction('workcore.appointment.change_status', [
                    'appointment_public_id' => $entityId,
                    'status' => $remaining['status'],
                    'reason' => $reason,
                ], ['status']);
                unset($remaining['status']);
            }
        }

        if ($resourceType === 'support_ticket') {
            if (array_key_exists('assigned_worker_id', $remaining)) {
                $workerPublicId = $this->workerPublicId($companyId, $remaining['assigned_worker_id']);
                $operations[] = $this->businessAction('workcore.support_ticket.assign', [
                    'support_ticket_public_id' => $entityId,
                    'worker_public_id' => $workerPublicId,
                    'reason' => $reason,
                ], ['assigned_worker_id']);
                unset($remaining['assigned_worker_id']);
            }
            if (array_key_exists('status', $remaining)) {
                $operations[] = $this->businessAction('workcore.support_ticket.change_status', [
                    'support_ticket_public_id' => $entityId,
                    'status' => $remaining['status'],
                    'reason' => $reason,
                ], ['status']);
                unset($remaining['status']);
            }
        }

        if ($remaining !== []) {
            if (! (bool) config('workcore.rewind.branch_execution.allow_restore_fallback', true)) {
                throw new \RuntimeException('Temporal branch restore fallback is disabled.');
            }
            $allowed = array_values(array_filter((array) ($definition['restore_fields'] ?? []), 'is_string'));
            $blocked = array_values(array_diff(array_map('strval', array_keys($remaining)), $allowed));
            if ($blocked !== []) {
                throw new \RuntimeException('Temporal branch contains fields outside the restore allowlist: '.implode(', ', $blocked).'.');
            }
            $operations[] = [
                'executor' => 'restore_patch',
                'state' => $remaining,
                'fields' => array_values(array_map('strval', array_keys($remaining))),
            ];
        }

        if ($operations === []) {
            throw new \RuntimeException('Temporal branch item produced no executable operations.');
        }

        return $operations;
    }

    /** @param array<string,mixed> $payload @param list<string> $fields @return array<string,mixed> */
    private function businessAction(string $key, array $payload, array $fields): array
    {
        if (! $this->actions->has($key)) {
            throw new \RuntimeException("Required WorkCore business action [{$key}] is not registered.");
        }

        return [
            'executor' => 'business_action',
            'action_key' => $key,
            'payload' => $payload,
            'fields' => $fields,
        ];
    }

    private function workerPublicId(int $companyId, mixed $workerId): string
    {
        if (! is_numeric($workerId) || (int) $workerId < 1) {
            throw new \RuntimeException('Support assignment requires a valid worker ID.');
        }
        $publicId = $this->db->table('tz_workers')
            ->where('company_id', $companyId)
            ->where('id', (int) $workerId)
            ->value('public_id');
        if (! is_string($publicId) || $publicId === '') {
            throw new \RuntimeException('Assigned worker is unavailable inside the branch execution tenant.');
        }
        return $publicId;
    }
}
