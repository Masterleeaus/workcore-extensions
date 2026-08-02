<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\Modules\Operations\Services\WorkOrderStatusPolicy;
use App\Domains\WorkCore\System\Modules\Operations\Services\WorkOrderTaskStatusPolicy;
use App\Domains\WorkCore\System\Modules\Operations\Services\WorkOrderTemplateExpander;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentWorkOrderRepository extends TenantScopedRepository implements WorkOrderRepositoryContract
{
    private const WORK_ORDERS = 'tz_work_orders';


    public function __construct(
        ConnectionInterface $db,
        \App\Domains\WorkCore\System\Contracts\TenantContextContract $tenant,
        private CompanyRecordAuthorizer $authorizer,
        private WorkOrderStatusPolicy $statusPolicy,
        private WorkOrderTaskStatusPolicy $taskStatusPolicy,
        private WorkOrderTemplateExpander $templateExpander,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        ?string $status = null,
        ?string $customerPublicId = null,
        ?string $premisesPublicId = null,
        int $perPage = 25,
        ?string $businessLinePublicId = null,
    ): LengthAwarePaginator {
        $this->assertCompany($companyId);

        $builder = $this->baseQuery($companyId);
        $this->authorizer->apply(
            $builder,
            $accessLevel,
            $actorId,
            'work_orders.created_by_user_id',
            ['work_orders.created_by_user_id'],
        );

        $builder->when($query, static fn ($queryBuilder) => $queryBuilder->where(static function ($nested) use ($query): void {
            $nested->where('work_orders.number', 'like', "%{$query}%")
                ->orWhere('work_orders.title', 'like', "%{$query}%")
                ->orWhere('work_orders.description', 'like', "%{$query}%")
                ->orWhere('customers.name', 'like', "%{$query}%")
                ->orWhere('premises.name', 'like', "%{$query}%");
        }));
        $builder->when($status, static fn ($queryBuilder) => $queryBuilder->where('work_orders.status', $status));
        $builder->when($customerPublicId, static fn ($queryBuilder) => $queryBuilder->where('customers.public_id', $customerPublicId));
        $builder->when($premisesPublicId, static fn ($queryBuilder) => $queryBuilder->where('premises.public_id', $premisesPublicId));
        $builder->when($businessLinePublicId, static fn ($queryBuilder) => $queryBuilder->where('business_lines.public_id', $businessLinePublicId));

        return $builder->select($this->summaryColumns())
            ->orderByDesc('work_orders.created_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function create(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $businessLineId = $this->resolveBusinessLineId($companyId, $data['business_line_public_id'] ?? null);

        $customer = $this->db->table('tz_customers')
            ->where('company_id', $companyId)
            ->where('public_id', (string) $data['customer_public_id'])
            ->whereNull('deleted_at')
            ->first(['id', 'public_id', 'name']);
        if (! $customer) {
            throw new InvalidArgumentException('Customer does not belong to the active company.');
        }

        $premises = null;
        if (! empty($data['premises_public_id'])) {
            $premises = $this->db->table('tz_premises')
                ->where('company_id', $companyId)
                ->where('customer_id', $customer->id)
                ->where('public_id', (string) $data['premises_public_id'])
                ->whereNull('deleted_at')
                ->first(['id', 'public_id', 'name']);
            if (! $premises) {
                throw new InvalidArgumentException('Premises does not belong to the selected customer and active company.');
            }
        }

        $initialStatus = (string) ($data['status'] ?? 'draft');
        if (! in_array($initialStatus, ['draft', 'ready'], true)) {
            throw new InvalidArgumentException('A new work order must start as draft or ready.');
        }
        $priority = (string) ($data['priority'] ?? 'normal');
        if (! in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            throw new InvalidArgumentException('Invalid work order priority.');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $customer, $premises, $initialStatus, $priority, $businessLineId): array {
            $publicId = (string) Str::ulid();
            $number = trim((string) ($data['number'] ?? ''));
            if ($number === '') {
                $number = 'WO-' . now()->format('ymd') . '-' . Str::upper(substr($publicId, -6));
            }
            if ($this->db->table(self::WORK_ORDERS)->where('company_id', $companyId)->where('number', $number)->exists()) {
                throw new InvalidArgumentException('Work order number already exists for the active company.');
            }

            $workOrderId = $this->db->table(self::WORK_ORDERS)->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'business_line_id' => $businessLineId,
                'customer_id' => $customer->id,
                'premises_id' => $premises?->id,
                'number' => $number,
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $priority,
                'status' => $initialStatus,
                'source' => $data['source'] ?? 'manual',
                'due_at' => $data['due_at'] ?? null,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->appendStatusHistory(
                companyId: $companyId,
                workOrderId: $workOrderId,
                fromStatus: null,
                toStatus: $initialStatus,
                reason: 'Work order created.',
                actorId: $actorId,
            );

            foreach ((array) ($data['services'] ?? []) as $sortOrder => $serviceLine) {
                $this->templateExpander->expand(
                    companyId: $companyId,
                    workOrderId: $workOrderId,
                    actorId: $actorId,
                    serviceLine: (array) $serviceLine,
                    sortOrder: $sortOrder,
                    businessLineId: $businessLineId,
                );
            }

            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function changeStatus(string $workOrderPublicId, string $toStatus, ?string $reason, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($workOrderPublicId, $toStatus, $reason, $companyId, $actorId): array {
            $workOrder = $this->db->table(self::WORK_ORDERS)
                ->where('company_id', $companyId)
                ->where('public_id', $workOrderPublicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'status', 'started_at', 'completed_at', 'cancelled_at']);
            if (! $workOrder) {
                throw new InvalidArgumentException('Work order does not belong to the active company.');
            }

            $fromStatus = (string) $workOrder->status;
            if ($fromStatus === $toStatus) {
                return $this->findByPublicId($companyId, $workOrderPublicId) ?? ['public_id' => $workOrderPublicId];
            }
            $incompleteRequiredTasks = $toStatus === 'completed'
                ? $this->db->table('tz_work_order_tasks')
                    ->where('company_id', $companyId)
                    ->where('work_order_id', $workOrder->id)
                    ->where('is_required', true)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['completed', 'skipped'])
                    ->count()
                : 0;
            $this->statusPolicy->assertTransition($fromStatus, $toStatus, $reason, $incompleteRequiredTasks);

            $timestamps = [
                'started_at' => $toStatus === 'in_progress' && $workOrder->started_at === null ? now() : null,
                'completed_at' => $toStatus === 'completed' ? now() : null,
                'cancelled_at' => $toStatus === 'cancelled' ? now() : null,
            ];
            $updates = [
                'status' => $toStatus,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ];
            foreach ($timestamps as $column => $value) {
                if ($value !== null) {
                    $updates[$column] = $value;
                }
            }
            if ($fromStatus === 'completed' && $toStatus === 'in_progress') {
                $updates['completed_at'] = null;
            }
            if ($fromStatus === 'cancelled' && $toStatus === 'draft') {
                $updates['cancelled_at'] = null;
            }

            $this->db->table(self::WORK_ORDERS)->where('id', $workOrder->id)->update($updates);
            $this->appendStatusHistory($companyId, $workOrder->id, $fromStatus, $toStatus, $reason, $actorId);

            return $this->findByPublicId($companyId, $workOrderPublicId) ?? ['public_id' => $workOrderPublicId];
        }, 3);
    }

    public function updateTaskStatus(string $taskPublicId, string $toStatus, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($taskPublicId, $toStatus, $companyId, $actorId): array {
            $task = $this->db->table('tz_work_order_tasks')
                ->where('company_id', $companyId)
                ->where('public_id', $taskPublicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'public_id', 'work_order_id', 'status']);
            if (! $task) {
                throw new InvalidArgumentException('Work order task does not belong to the active company.');
            }

            $fromStatus = (string) $task->status;
            $this->taskStatusPolicy->assertTransition($fromStatus, $toStatus);

            $this->db->table('tz_work_order_tasks')->where('id', $task->id)->update([
                'status' => $toStatus,
                'completed_at' => $toStatus === 'completed' ? now() : null,
                'completed_by_user_id' => $toStatus === 'completed' ? $actorId : null,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);

            $workOrderPublicId = (string) $this->db->table(self::WORK_ORDERS)
                ->where('company_id', $companyId)
                ->where('id', $task->work_order_id)
                ->value('public_id');

            return [
                'public_id' => $task->public_id,
                'work_order_public_id' => $workOrderPublicId,
                'from_status' => $fromStatus,
                'status' => $toStatus,
            ];
        }, 3);
    }

    public function addTimeEntry(string $workOrderPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        $workOrder = $this->db->table(self::WORK_ORDERS)
            ->where('company_id', $companyId)
            ->where('public_id', $workOrderPublicId)
            ->whereNull('deleted_at')
            ->first(['id']);
        if (! $workOrder) {
            throw new InvalidArgumentException('Work order does not belong to the active company.');
        }

        $taskId = null;
        if (! empty($data['task_public_id'])) {
            $taskId = $this->db->table('tz_work_order_tasks')
                ->where('company_id', $companyId)
                ->where('work_order_id', $workOrder->id)
                ->where('public_id', (string) $data['task_public_id'])
                ->whereNull('deleted_at')
                ->value('id');
            if (! $taskId) {
                throw new InvalidArgumentException('Task does not belong to the selected work order and active company.');
            }
        }

        $userId = (int) ($data['user_id'] ?? $actorId);
        if (! $this->db->table('tz_company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists()) {
            throw new InvalidArgumentException('Time-entry user is not an active member of the company.');
        }

        $startedAt = CarbonImmutable::parse((string) $data['started_at']);
        $endedAt = ! empty($data['ended_at']) ? CarbonImmutable::parse((string) $data['ended_at']) : null;
        if ($endedAt !== null && $endedAt->lessThanOrEqualTo($startedAt)) {
            throw new InvalidArgumentException('Time entry end must be after its start.');
        }
        $durationMinutes = $endedAt
            ? (int) $startedAt->diffInMinutes($endedAt)
            : (int) ($data['duration_minutes'] ?? 0);
        if ($durationMinutes < 1) {
            throw new InvalidArgumentException('Time entry duration must be at least one minute.');
        }

        $publicId = (string) Str::ulid();
        $this->db->table('tz_time_entries')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'work_order_id' => $workOrder->id,
            'work_order_task_id' => $taskId,
            'user_id' => $userId,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'notes' => $data['notes'] ?? null,
            'is_billable' => $data['is_billable'] ?? true,
            'status' => 'recorded',
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $this->db->table('tz_time_entries as entries')
            ->leftJoin('tz_work_order_tasks as tasks', 'tasks.id', '=', 'entries.work_order_task_id')
            ->where('entries.company_id', $companyId)
            ->where('entries.public_id', $publicId)
            ->first([
                'entries.public_id',
                'tasks.public_id as task_public_id',
                'entries.user_id',
                'entries.started_at',
                'entries.ended_at',
                'entries.duration_minutes',
                'entries.notes',
                'entries.is_billable',
                'entries.status',
            ]);

        return $row ? (array) $row : ['public_id' => $publicId];
    }

    public function findByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);

        $row = $this->baseQuery($companyId)
            ->where('work_orders.public_id', $publicId)
            ->first($this->summaryColumns());
        if (! $row) {
            return null;
        }

        $workOrderId = (int) $row->internal_id;
        $result = (array) $row;
        unset($result['internal_id']);

        $result['services'] = $this->db->table('tz_work_order_services')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->orderBy('sort_order')
            ->get([
                'public_id', 'service_code', 'service_name', 'description', 'quantity', 'unit_price',
                'estimated_duration_minutes', 'sort_order', 'status',
            ])->map(static fn ($item): array => (array) $item)->all();

        $result['tasks'] = $this->db->table('tz_work_order_tasks')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get([
                'public_id', 'name', 'description', 'sort_order', 'estimated_minutes', 'is_required',
                'status', 'completed_at', 'completed_by_user_id',
            ])->map(static fn ($item): array => (array) $item)->all();

        $result['time_entries'] = $this->db->table('tz_time_entries as entries')
            ->leftJoin('tz_work_order_tasks as tasks', 'tasks.id', '=', 'entries.work_order_task_id')
            ->where('entries.company_id', $companyId)
            ->where('entries.work_order_id', $workOrderId)
            ->whereNull('entries.deleted_at')
            ->orderByDesc('entries.started_at')
            ->get([
                'entries.public_id', 'tasks.public_id as task_public_id', 'entries.user_id',
                'entries.started_at', 'entries.ended_at', 'entries.duration_minutes', 'entries.notes',
                'entries.is_billable', 'entries.status',
            ])->map(static fn ($item): array => (array) $item)->all();

        $result['status_history'] = $this->db->table('tz_work_order_status_history')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->orderBy('changed_at')
            ->get(['public_id', 'from_status', 'to_status', 'reason', 'changed_by_user_id', 'changed_at'])
            ->map(static fn ($item): array => (array) $item)->all();

        return $result;
    }

    private function appendStatusHistory(
        int $companyId,
        int $workOrderId,
        ?string $fromStatus,
        string $toStatus,
        ?string $reason,
        int $actorId,
    ): void {
        $this->db->table('tz_work_order_status_history')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'work_order_id' => $workOrderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'changed_by_user_id' => $actorId,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function baseQuery(int $companyId): \Illuminate\Database\Query\Builder
    {
        return $this->db->table(self::WORK_ORDERS . ' as work_orders')
            ->join('tz_customers as customers', function ($join): void {
                $join->on('customers.id', '=', 'work_orders.customer_id')
                    ->on('customers.company_id', '=', 'work_orders.company_id');
            })
            ->leftJoin('tz_premises as premises', function ($join): void {
                $join->on('premises.id', '=', 'work_orders.premises_id')
                    ->on('premises.company_id', '=', 'work_orders.company_id')
                    ->whereNull('premises.deleted_at');
            })
            ->leftJoin('tz_business_lines as business_lines', function ($join): void {
                $join->on('business_lines.id', '=', 'work_orders.business_line_id')
                    ->on('business_lines.company_id', '=', 'work_orders.company_id')
                    ->whereNull('business_lines.deleted_at');
            })
            ->where('work_orders.company_id', $companyId)
            ->whereNull('work_orders.deleted_at')
            ->whereNull('customers.deleted_at');
    }

    /** @return array<int,string> */
    private function summaryColumns(): array
    {
        return [
            'work_orders.id as internal_id',
            'work_orders.public_id',
            'work_orders.number',
            'business_lines.public_id as business_line_public_id',
            'business_lines.name as business_line_name',
            'customers.public_id as customer_public_id',
            'customers.name as customer_name',
            'premises.public_id as premises_public_id',
            'premises.name as premises_name',
            'work_orders.title',
            'work_orders.description',
            'work_orders.priority',
            'work_orders.status',
            'work_orders.source',
            'work_orders.due_at',
            'work_orders.started_at',
            'work_orders.completed_at',
            'work_orders.cancelled_at',
            'work_orders.created_at',
            'work_orders.updated_at',
        ];
    }

    private function resolveBusinessLineId(int $companyId, mixed $publicId): ?int
    {
        $publicId = trim((string) $publicId);
        if ($publicId === '') {
            return null;
        }

        $id = $this->db->table('tz_business_lines')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('id');
        if ($id === null) {
            throw new InvalidArgumentException('Business line does not belong to the active company or is inactive.');
        }

        return (int) $id;
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }
}
