<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\AccommodationActionSupport;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\HousekeepingState;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;

final class UpdateAccommodationHousekeeping implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $result = $this->db->transaction(function () use ($request): array {
            $task = $this->records->housekeepingTask(
                $request->companyId,
                (string) $request->payload['task_public_id'],
                true,
            );
            $target = (string) $request->payload['status'];
            HousekeepingState::assertTransition((string) $task->status, $target);

            $workerId = array_key_exists('assigned_worker_public_id', $request->payload)
                ? $this->records->workerId($request->companyId, $request->payload['assigned_worker_public_id'])
                : ($task->assigned_worker_id !== null ? (int) $task->assigned_worker_id : null);
            if (in_array($target, ['assigned', 'in_progress'], true) && $workerId === null) {
                throw new DomainException('An assigned worker is required before housekeeping can start.');
            }

            $updates = [
                'status' => $target,
                'assigned_worker_id' => $workerId,
                'priority' => (string) ($request->payload['priority'] ?? $task->priority),
                'due_at' => $request->payload['due_at'] ?? $task->due_at,
                'condition_before' => $request->payload['condition_before'] ?? $task->condition_before,
                'condition_after' => $request->payload['condition_after'] ?? $task->condition_after,
                'notes' => $request->payload['notes'] ?? $task->notes,
                'metadata' => json_encode(array_replace(
                    $this->records->decode($task->metadata ?? null),
                    (array) ($request->payload['metadata'] ?? []),
                ), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ];
            if ($target === 'in_progress' && $task->started_at === null) $updates['started_at'] = now();
            if ($target === 'cleaned') $updates['completed_at'] = now();
            if ($target === 'inspected') {
                $updates['inspected_at'] = now();
                $updates['inspected_by_user_id'] = $request->actorId;
            }
            if ($target === 'ready') {
                $updates['inspected_at'] = $task->inspected_at ?? now();
                $updates['inspected_by_user_id'] = $task->inspected_by_user_id ?? $request->actorId;
            }
            $this->db->table('pm_accommodation_housekeeping_tasks')->where('id', (int) $task->id)->update($updates);

            $spaceStatus = match ($target) {
                'ready' => 'available',
                'blocked' => 'maintenance',
                'cancelled' => 'available',
                default => 'cleaning',
            };
            $this->db->table('pm_premise_spaces')->where('company_id', $request->companyId)
                ->where('id', (int) $task->space_id)->update(['availability_status' => $spaceStatus, 'updated_at' => now()]);

            $fresh = (array) $this->records->housekeepingTask($request->companyId, (string) $task->public_id);
            foreach (['id','company_id','premise_id','space_id','reservation_id','stay_id','work_order_id','assigned_worker_id','inspected_by_user_id'] as $field) unset($fresh[$field]);
            $fresh['metadata'] = $this->records->decode($fresh['metadata'] ?? null);
            $fresh['space_public_id'] = (string) $this->db->table('pm_premise_spaces')->where('id', (int) $task->space_id)->value('public_id');
            $fresh['assigned_worker_public_id'] = $workerId === null ? null : (string) $this->db->table('tz_workers')->where('id', $workerId)->value('public_id');
            return $fresh;
        }, 3);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('accommodation_housekeeping_task', (string) $result['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.housekeeping.status_changed', 1, ['task' => $result])],
        );
    }
}
