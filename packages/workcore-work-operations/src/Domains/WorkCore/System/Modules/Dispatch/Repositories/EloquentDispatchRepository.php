<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Repositories;

use App\Domains\WorkCore\System\Authorization\{CompanyRecordAuthorizer,WorkCoreAccessLevel};
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Contracts\DispatchRepositoryContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Services\{DispatchStatusPolicy,DispatchWindowPolicy};
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentDispatchRepository extends TenantScopedRepository implements DispatchRepositoryContract
{
    private const ASSIGNMENTS = 'tz_dispatch_assignments';
    private const ACTIVE_STATUSES = ['assigned', 'dispatched', 'en_route', 'arrived', 'in_progress'];

    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CompanyRecordAuthorizer $authorizer,
        private CompanyScopedReferenceValidator $references,
        private DispatchStatusPolicy $statusPolicy,
        private DispatchWindowPolicy $windowPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        ?string $status = null,
        ?string $assignmentType = null,
        ?int $assignedUserId = null,
        ?string $premisesPublicId = null,
        ?string $scheduledFrom = null,
        ?string $scheduledTo = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $this->assertCompany($companyId);
        $builder = $this->baseQuery($companyId);
        $this->authorizer->apply($builder, $accessLevel, $actorId, 'dispatches.created_by_user_id', ['dispatches.assigned_user_id']);
        $builder->when($query, static fn (Builder $q) => $q->where(static function (Builder $nested) use ($query): void {
            $nested->where('dispatches.dispatch_number', 'like', "%{$query}%")
                ->orWhere('dispatches.instructions', 'like', "%{$query}%")
                ->orWhere('work_orders.number', 'like', "%{$query}%")
                ->orWhere('work_orders.title', 'like', "%{$query}%")
                ->orWhere('appointments.title', 'like', "%{$query}%")
                ->orWhere('premises.name', 'like', "%{$query}%");
        }));
        $builder->when($status, static fn (Builder $q) => $q->where('dispatches.status', $status));
        $builder->when($assignmentType, static fn (Builder $q) => $q->where('dispatches.assignment_type', $assignmentType));
        $builder->when($assignedUserId, static fn (Builder $q) => $q->where('dispatches.assigned_user_id', $assignedUserId));
        $builder->when($premisesPublicId, static fn (Builder $q) => $q->where('premises.public_id', $premisesPublicId));
        $builder->when($scheduledFrom, static fn (Builder $q) => $q->where('dispatches.scheduled_end', '>=', $scheduledFrom));
        $builder->when($scheduledTo, static fn (Builder $q) => $q->where('dispatches.scheduled_start', '<=', $scheduledTo));
        return $builder->select($this->summaryColumns())
            ->orderByRaw('CASE WHEN dispatches.sequence IS NULL THEN 1 ELSE 0 END')
            ->orderBy('dispatches.sequence')
            ->orderBy('dispatches.scheduled_start')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function create(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $assignedUserId = (int) $data['assigned_user_id'];
        $this->references->requireActiveMember($companyId, $assignedUserId, 'assigned_user_id');
        $relationships = $this->resolveRelationships($data, $companyId);
        $window = $this->normaliseOptionalWindow($data);
        if ($window !== null) {
            $this->assertNoCapacityConflict($companyId, $assignedUserId, $window['scheduled_start']->format('Y-m-d H:i:s'), $window['scheduled_end']->format('Y-m-d H:i:s'), null, $relationships['appointment_id'], (bool) ($data['allow_conflicts'] ?? false));
        }
        return $this->db->transaction(function () use ($data, $companyId, $actorId, $assignedUserId, $relationships, $window): array {
            $publicId = (string) Str::ulid();
            $number = trim((string) ($data['dispatch_number'] ?? ''));
            if ($number === '') {
                $number = 'DSP-' . now()->format('ymd') . '-' . Str::upper(substr($publicId, -6));
            }
            if ($this->db->table(self::ASSIGNMENTS)->where('company_id', $companyId)->where('dispatch_number', $number)->exists()) {
                throw new InvalidArgumentException('Dispatch number already exists for the active company.');
            }
            $dispatchId = $this->db->table(self::ASSIGNMENTS)->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'dispatch_number' => $number,
                'work_order_id' => $relationships['work_order_id'],
                'appointment_id' => $relationships['appointment_id'],
                'premises_id' => $relationships['premises_id'],
                'assigned_user_id' => $assignedUserId,
                'assignment_type' => (string) ($data['assignment_type'] ?? $relationships['suggested_type']),
                'priority' => (string) ($data['priority'] ?? $relationships['priority'] ?? 'normal'),
                'status' => 'assigned',
                'scheduled_start' => $window?->get('scheduled_start')?->format('Y-m-d H:i:s'),
                'scheduled_end' => $window?->get('scheduled_end')?->format('Y-m-d H:i:s'),
                'timezone' => $window?->get('timezone') ?? (string) config('workcore.context.default_timezone', 'Australia/Melbourne'),
                'route_group' => $data['route_group'] ?? null,
                'sequence' => $data['sequence'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'access_notes' => $data['access_notes'] ?? $relationships['access_notes'],
                'internal_notes' => $data['internal_notes'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->appendEvent($companyId, $dispatchId, 'assigned', null, 'assigned', 'Dispatch assignment created.', $actorId, []);
            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function reassign(string $dispatchPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $newUserId = (int) $data['assigned_user_id'];
        $this->references->requireActiveMember($companyId, $newUserId, 'assigned_user_id');
        return $this->db->transaction(function () use ($dispatchPublicId, $data, $companyId, $actorId, $newUserId): array {
            $dispatch = $this->db->table(self::ASSIGNMENTS)->where('company_id', $companyId)->where('public_id', $dispatchPublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $dispatch) {
                throw new InvalidArgumentException('Dispatch assignment does not belong to the active company.');
            }
            if (in_array((string) $dispatch->status, ['completed'], true)) {
                throw new InvalidArgumentException('A completed dispatch cannot be reassigned.');
            }
            $window = null;
            if (! empty($data['scheduled_start']) || ! empty($data['scheduled_end'])) {
                $window = $this->normaliseOptionalWindow($data);
            } elseif ($dispatch->scheduled_start && $dispatch->scheduled_end) {
                $window = collect([
                    'scheduled_start' => \Carbon\CarbonImmutable::parse((string) $dispatch->scheduled_start, 'UTC'),
                    'scheduled_end' => \Carbon\CarbonImmutable::parse((string) $dispatch->scheduled_end, 'UTC'),
                    'timezone' => (string) $dispatch->timezone,
                ]);
            }
            if ($window !== null) {
                $this->assertNoCapacityConflict($companyId, $newUserId, $window->get('scheduled_start')->format('Y-m-d H:i:s'), $window->get('scheduled_end')->format('Y-m-d H:i:s'), (int) $dispatch->id, $dispatch->appointment_id ? (int) $dispatch->appointment_id : null, (bool) ($data['allow_conflicts'] ?? false));
            }
            $reopening = in_array((string) $dispatch->status, ['cancelled', 'failed'], true);
            $updates = [
                'assigned_user_id' => $newUserId,
                'status' => $reopening ? 'assigned' : $dispatch->status,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ];
            if ($reopening) {
                $updates['cancelled_at'] = null;
                $updates['failed_at'] = null;
            }
            if ($window !== null && (! empty($data['scheduled_start']) || ! empty($data['scheduled_end']))) {
                $updates['scheduled_start'] = $window->get('scheduled_start')->format('Y-m-d H:i:s');
                $updates['scheduled_end'] = $window->get('scheduled_end')->format('Y-m-d H:i:s');
                $updates['timezone'] = $window->get('timezone');
            }
            $this->db->table(self::ASSIGNMENTS)->where('id', $dispatch->id)->update($updates);
            $this->appendEvent($companyId, (int) $dispatch->id, 'reassigned', (string) $dispatch->status, (string) $updates['status'], (string) $data['reason'], $actorId, [
                'from_user_id' => $dispatch->assigned_user_id,
                'to_user_id' => $newUserId,
            ]);
            return $this->findByPublicId($companyId, $dispatchPublicId) ?? ['public_id' => $dispatchPublicId];
        }, 3);
    }

    public function changeStatus(string $dispatchPublicId, string $toStatus, ?string $reason, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($dispatchPublicId, $toStatus, $reason, $data, $companyId, $actorId): array {
            $dispatch = $this->db->table(self::ASSIGNMENTS)->where('company_id', $companyId)->where('public_id', $dispatchPublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $dispatch) {
                throw new InvalidArgumentException('Dispatch assignment does not belong to the active company.');
            }
            $fromStatus = (string) $dispatch->status;
            $this->statusPolicy->assertTransition($fromStatus, $toStatus, $reason);
            if ($fromStatus === $toStatus) {
                return $this->findByPublicId($companyId, $dispatchPublicId) ?? ['public_id' => $dispatchPublicId];
            }
            $updates = ['status' => $toStatus, 'updated_by_user_id' => $actorId, 'updated_at' => now()];
            $timestampColumn = match ($toStatus) {
                'dispatched' => 'dispatched_at', 'en_route' => 'en_route_at', 'arrived' => 'arrived_at',
                'in_progress' => 'started_at', 'completed' => 'completed_at', 'cancelled' => 'cancelled_at',
                'failed' => 'failed_at', default => null,
            };
            if ($timestampColumn !== null) {
                $updates[$timestampColumn] = now();
            }
            if ($toStatus === 'assigned' && in_array($fromStatus, ['cancelled', 'failed'], true)) {
                $updates['cancelled_at'] = null;
                $updates['failed_at'] = null;
            }
            if (array_key_exists('travel_distance_meters', $data)) {
                $updates['travel_distance_meters'] = $data['travel_distance_meters'];
            }
            if (array_key_exists('travel_duration_seconds', $data)) {
                $updates['travel_duration_seconds'] = $data['travel_duration_seconds'];
            }
            $this->db->table(self::ASSIGNMENTS)->where('id', $dispatch->id)->update($updates);
            $metadata = (array) ($data['metadata'] ?? []);
            if (isset($data['travel_distance_meters'])) $metadata['travel_distance_meters'] = $data['travel_distance_meters'];
            if (isset($data['travel_duration_seconds'])) $metadata['travel_duration_seconds'] = $data['travel_duration_seconds'];
            $this->appendEvent($companyId, (int) $dispatch->id, 'status_changed', $fromStatus, $toStatus, $reason, $actorId, $metadata, $data['latitude'] ?? null, $data['longitude'] ?? null);
            return $this->findByPublicId($companyId, $dispatchPublicId) ?? ['public_id' => $dispatchPublicId];
        }, 3);
    }

    public function findByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->baseQuery($companyId)->where('dispatches.public_id', $publicId)->first($this->summaryColumns());
        if (! $row) return null;
        $result = (array) $row;
        $internalId = (int) $result['internal_id'];
        unset($result['internal_id']);
        $result['events'] = $this->db->table('tz_dispatch_events')->where('company_id', $companyId)->where('dispatch_assignment_id', $internalId)->orderBy('occurred_at')->get([
            'public_id','event_type','from_status','to_status','reason','latitude','longitude','metadata','changed_by_user_id','occurred_at',
        ])->map(static function ($row): array {
            $item = (array) $row;
            $item['metadata'] = $item['metadata'] ? json_decode((string) $item['metadata'], true) : [];
            return $item;
        })->all();
        return $result;
    }

    /** @param array<string,mixed> $data @return array{work_order_id:?int,appointment_id:?int,premises_id:?int,suggested_type:string,priority:string,access_notes:?string} */
    private function resolveRelationships(array $data, int $companyId): array
    {
        $workOrder = null;
        $appointment = null;
        if (! empty($data['work_order_public_id'])) {
            $workOrder = $this->db->table('tz_work_orders')->where('company_id', $companyId)->where('public_id', (string) $data['work_order_public_id'])->whereNull('deleted_at')->first(['id','premises_id','priority','title']);
            if (! $workOrder) throw new InvalidArgumentException('Work order does not belong to the active company.');
        }
        if (! empty($data['appointment_public_id'])) {
            $appointment = $this->db->table('tz_appointments')->where('company_id', $companyId)->where('public_id', (string) $data['appointment_public_id'])->whereNull('deleted_at')->first(['id','work_order_id','premises_id','appointment_type','access_notes','starts_at','ends_at','timezone']);
            if (! $appointment) throw new InvalidArgumentException('Appointment does not belong to the active company.');
        }
        if (! $workOrder && ! $appointment) throw new InvalidArgumentException('A dispatch must reference a work order or appointment.');
        if ($workOrder && $appointment && $appointment->work_order_id && (int) $appointment->work_order_id !== (int) $workOrder->id) {
            throw new InvalidArgumentException('The appointment does not belong to the selected work order.');
        }
        $premisesIds = array_values(array_unique(array_filter([$workOrder?->premises_id, $appointment?->premises_id])));
        if (count($premisesIds) > 1) throw new InvalidArgumentException('Work order and appointment premises do not match.');
        return [
            'work_order_id' => $workOrder?->id ?? ($appointment?->work_order_id ? (int) $appointment->work_order_id : null),
            'appointment_id' => $appointment?->id,
            'premises_id' => $premisesIds[0] ?? null,
            'suggested_type' => (string) ($appointment?->appointment_type ?? 'job'),
            'priority' => (string) ($workOrder?->priority ?? 'normal'),
            'access_notes' => $appointment?->access_notes,
        ];
    }

    /** @param array<string,mixed> $data */
    private function normaliseOptionalWindow(array $data): ?\Illuminate\Support\Collection
    {
        if (empty($data['scheduled_start']) && empty($data['scheduled_end'])) {
            if (! empty($data['appointment_public_id'])) {
                $appointment = $this->db->table('tz_appointments')->where('company_id', $this->companyId())->where('public_id', (string) $data['appointment_public_id'])->whereNull('deleted_at')->first(['starts_at','ends_at','timezone']);
                if ($appointment) return collect([
                    'scheduled_start' => \Carbon\CarbonImmutable::parse((string) $appointment->starts_at, 'UTC'),
                    'scheduled_end' => \Carbon\CarbonImmutable::parse((string) $appointment->ends_at, 'UTC'),
                    'timezone' => (string) $appointment->timezone,
                ]);
            }
            return null;
        }
        if (empty($data['scheduled_start']) || empty($data['scheduled_end'])) throw new InvalidArgumentException('Both dispatch start and end are required.');
        return collect($this->windowPolicy->normalise((string) $data['scheduled_start'], (string) $data['scheduled_end'], (string) ($data['timezone'] ?? config('workcore.context.default_timezone', 'Australia/Melbourne'))));
    }

    private function assertNoCapacityConflict(int $companyId, int $userId, string $start, string $end, ?int $ignoreId, ?int $ignoreAppointmentId, bool $allowConflicts): void
    {
        if ($allowConflicts) return;
        $dispatchConflict = $this->db->table(self::ASSIGNMENTS)
            ->where('company_id', $companyId)->where('assigned_user_id', $userId)->whereNull('deleted_at')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($ignoreId, static fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where('scheduled_start', '<', $end)->where('scheduled_end', '>', $start)->exists();
        if ($dispatchConflict) throw new InvalidArgumentException('The selected team member already has an overlapping dispatch assignment.');
        $appointmentConflict = $this->db->table('tz_appointment_participants as participants')
            ->join('tz_appointments as appointments', 'appointments.id', '=', 'participants.appointment_id')
            ->where('participants.company_id', $companyId)->where('participants.user_id', $userId)
            ->whereIn('appointments.status', ['tentative','scheduled','confirmed','in_progress'])
            ->when($ignoreAppointmentId, static fn (Builder $q) => $q->where('appointments.id', '!=', $ignoreAppointmentId))
            ->whereNull('appointments.deleted_at')->where('appointments.starts_at', '<', $end)->where('appointments.ends_at', '>', $start)->exists();
        if ($appointmentConflict) throw new InvalidArgumentException('The selected team member already has an overlapping appointment.');
        if ((bool) config('workcore.attendance.rules.prevent_dispatch_during_approved_leave', true)) {
            $workerId = $this->db->table('tz_workers')->where('company_id', $companyId)->where('user_id', $userId)->whereNull('deleted_at')->value('id');
            if ($workerId) {
                $leaveConflict = $this->db->table('tz_leave_requests')->where('company_id', $companyId)->where('worker_id', $workerId)
                    ->where('status', 'approved')->whereNull('deleted_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
                if ($leaveConflict) throw new InvalidArgumentException('The selected team member has approved leave during this dispatch window.');
            }
        }
    }

    private function appendEvent(int $companyId, int $dispatchId, string $eventType, ?string $fromStatus, ?string $toStatus, ?string $reason, int $actorId, array $metadata, mixed $latitude = null, mixed $longitude = null): void
    {
        $this->db->table('tz_dispatch_events')->insert([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'dispatch_assignment_id' => $dispatchId,
            'event_type' => $eventType, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'reason' => $reason,
            'latitude' => $latitude, 'longitude' => $longitude, 'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'changed_by_user_id' => $actorId, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) throw new InvalidArgumentException('Dispatch actor does not match the active user.');
        $this->references->requireActiveMember($companyId, $actorId, 'actor_id');
    }

    private function baseQuery(int $companyId): Builder
    {
        return $this->db->table(self::ASSIGNMENTS . ' as dispatches')
            ->leftJoin('tz_work_orders as work_orders', 'work_orders.id', '=', 'dispatches.work_order_id')
            ->leftJoin('tz_appointments as appointments', 'appointments.id', '=', 'dispatches.appointment_id')
            ->leftJoin('tz_premises as premises', 'premises.id', '=', 'dispatches.premises_id')
            ->leftJoin('users as assignees', 'assignees.id', '=', 'dispatches.assigned_user_id')
            ->where('dispatches.company_id', $companyId)->whereNull('dispatches.deleted_at');
    }

    /** @return list<string> */
    private function summaryColumns(): array
    {
        return [
            'dispatches.id as internal_id','dispatches.public_id','dispatches.dispatch_number','work_orders.public_id as work_order_public_id',
            'work_orders.number as work_order_number','appointments.public_id as appointment_public_id','appointments.title as appointment_title',
            'premises.public_id as premises_public_id','premises.name as premises_name','dispatches.assigned_user_id',
            'assignees.name as assigned_user_name','dispatches.assignment_type','dispatches.priority','dispatches.status',
            'dispatches.scheduled_start','dispatches.scheduled_end','dispatches.timezone','dispatches.route_group','dispatches.sequence',
            'dispatches.instructions','dispatches.access_notes','dispatches.internal_notes','dispatches.travel_distance_meters',
            'dispatches.travel_duration_seconds','dispatches.source','dispatches.dispatched_at','dispatches.en_route_at',
            'dispatches.arrived_at','dispatches.started_at','dispatches.completed_at','dispatches.cancelled_at','dispatches.failed_at',
            'dispatches.created_at','dispatches.updated_at',
        ];
    }
}
