<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Assets\Services\AssetSchedulePolicy;
use App\Domains\WorkCore\System\Modules\Scheduling\Contracts\AppointmentRepositoryContract;
use App\Domains\WorkCore\System\Modules\Scheduling\Services\AppointmentSchedulePolicy;
use App\Domains\WorkCore\System\Modules\Scheduling\Services\AppointmentStatusPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentAppointmentRepository extends TenantScopedRepository implements AppointmentRepositoryContract
{
    private const APPOINTMENTS = 'tz_appointments';
    private const ACTIVE_STATUSES = ['tentative', 'scheduled', 'confirmed', 'in_progress'];

    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CompanyRecordAuthorizer $authorizer,
        private AppointmentSchedulePolicy $schedulePolicy,
        private AppointmentStatusPolicy $statusPolicy,
        private AssetSchedulePolicy $assetSchedulePolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        ?string $status = null,
        ?string $appointmentType = null,
        ?string $customerPublicId = null,
        ?string $premisesPublicId = null,
        ?string $workOrderPublicId = null,
        ?string $startsFrom = null,
        ?string $startsTo = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $this->assertCompany($companyId);
        $builder = $this->baseQuery($companyId);
        $this->authorizer->apply(
            $builder,
            $accessLevel,
            $actorId,
            'appointments.created_by_user_id',
            ['appointments.created_by_user_id'],
        );

        $builder->when($query, static fn (Builder $queryBuilder) => $queryBuilder->where(static function (Builder $nested) use ($query): void {
            $nested->where('appointments.title', 'like', "%{$query}%")
                ->orWhere('appointments.description', 'like', "%{$query}%")
                ->orWhere('customers.name', 'like', "%{$query}%")
                ->orWhere('premises.name', 'like', "%{$query}%")
                ->orWhere('work_orders.number', 'like', "%{$query}%");
        }));
        $builder->when($status, static fn (Builder $q) => $q->where('appointments.status', $status));
        $builder->when($appointmentType, static fn (Builder $q) => $q->where('appointments.appointment_type', $appointmentType));
        $builder->when($customerPublicId, static fn (Builder $q) => $q->where('customers.public_id', $customerPublicId));
        $builder->when($premisesPublicId, static fn (Builder $q) => $q->where('premises.public_id', $premisesPublicId));
        $builder->when($workOrderPublicId, static fn (Builder $q) => $q->where('work_orders.public_id', $workOrderPublicId));
        $builder->when($startsFrom, static fn (Builder $q) => $q->where('appointments.starts_at', '>=', $startsFrom));
        $builder->when($startsTo, static fn (Builder $q) => $q->where('appointments.starts_at', '<=', $startsTo));

        return $builder->select($this->summaryColumns())
            ->orderBy('appointments.starts_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function create(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $relationships = $this->resolveRelationships($data, $companyId);
        $timezone = (string) ($data['timezone'] ?? config('workcore.context.default_timezone', 'Australia/Melbourne'));
        $window = $this->schedulePolicy->normalise((string) $data['starts_at'], (string) $data['ends_at'], $timezone);
        $participants = $this->normaliseParticipants((array) ($data['participants'] ?? []), $companyId, $relationships['customer_id']);
        $assetInput = (array) ($data['assets'] ?? []);
        $status = (string) ($data['status'] ?? 'tentative');
        if (! in_array($status, ['tentative', 'scheduled', 'confirmed'], true)) {
            throw new InvalidArgumentException('A new appointment must start as tentative, scheduled, or confirmed.');
        }

        $this->assertNoConflicts(
            companyId: $companyId,
            startsAt: $window['starts_at']->format('Y-m-d H:i:s'),
            endsAt: $window['ends_at']->format('Y-m-d H:i:s'),
            premisesId: $relationships['premises_id'],
            participantUserIds: array_values(array_filter(array_column($participants, 'user_id'))),
            ignoreAppointmentId: null,
            allowConflicts: (bool) ($data['allow_conflicts'] ?? false),
            preventPremisesOverlap: (bool) ($data['prevent_premises_overlap'] ?? config('workcore.scheduling.conflicts.prevent_premises_overlap_by_default', false)),
        );

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $relationships, $window, $participants, $assetInput, $status): array {
            $assets = $this->assetSchedulePolicy->normaliseAndAssert(
                $assetInput,
                $companyId,
                $window['starts_at']->format('Y-m-d H:i:s'),
                $window['ends_at']->format('Y-m-d H:i:s'),
            );
            $publicId = (string) Str::ulid();
            $appointmentId = $this->db->table(self::APPOINTMENTS)->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'work_order_id' => $relationships['work_order_id'],
                'customer_id' => $relationships['customer_id'],
                'premises_id' => $relationships['premises_id'],
                'title' => (string) $data['title'],
                'description' => $data['description'] ?? null,
                'appointment_type' => $data['appointment_type'] ?? 'job',
                'status' => $status,
                'starts_at' => $window['starts_at']->format('Y-m-d H:i:s'),
                'ends_at' => $window['ends_at']->format('Y-m-d H:i:s'),
                'timezone' => $window['timezone'],
                'all_day' => (bool) ($data['all_day'] ?? false),
                'access_notes' => $data['access_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($participants as $participant) {
                $this->insertParticipant($companyId, $appointmentId, $participant);
            }
            foreach ($assets as $asset) {
                $this->insertAppointmentAsset($companyId, $appointmentId, $asset);
            }
            $this->appendStatusHistory($companyId, $appointmentId, null, $status, 'Appointment created.', $actorId);

            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function reschedule(string $appointmentPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($appointmentPublicId, $data, $companyId, $actorId): array {
            $appointment = $this->db->table(self::APPOINTMENTS)
                ->where('company_id', $companyId)
                ->where('public_id', $appointmentPublicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'premises_id', 'status', 'timezone']);
            if (! $appointment) {
                throw new InvalidArgumentException('Appointment does not belong to the active company.');
            }
            if ((string) $appointment->status === 'completed') {
                throw new InvalidArgumentException('A completed appointment cannot be rescheduled.');
            }

            $timezone = (string) ($data['timezone'] ?? $appointment->timezone ?? config('workcore.context.default_timezone', 'Australia/Melbourne'));
            $window = $this->schedulePolicy->normalise((string) $data['starts_at'], (string) $data['ends_at'], $timezone);
            $userIds = $this->db->table('tz_appointment_participants')
                ->where('company_id', $companyId)
                ->where('appointment_id', $appointment->id)
                ->whereNotNull('user_id')
                ->pluck('user_id')->map(static fn ($id): int => (int) $id)->all();

            $assetInput = array_key_exists('assets', $data)
                ? (array) $data['assets']
                : $this->db->table('tz_appointment_assets as appointment_assets')
                    ->join('tz_assets as assets', 'assets.id', '=', 'appointment_assets.asset_id')
                    ->where('appointment_assets.company_id', $companyId)
                    ->where('appointment_assets.appointment_id', $appointment->id)
                    ->get([
                        'assets.public_id as asset_public_id',
                        'appointment_assets.role',
                        'appointment_assets.is_required',
                        'appointment_assets.notes',
                    ])->map(static fn ($row): array => (array) $row)->all();
            $assets = $this->assetSchedulePolicy->normaliseAndAssert(
                $assetInput,
                $companyId,
                $window['starts_at']->format('Y-m-d H:i:s'),
                $window['ends_at']->format('Y-m-d H:i:s'),
                (int) $appointment->id,
            );

            $this->assertNoConflicts(
                companyId: $companyId,
                startsAt: $window['starts_at']->format('Y-m-d H:i:s'),
                endsAt: $window['ends_at']->format('Y-m-d H:i:s'),
                premisesId: $appointment->premises_id ? (int) $appointment->premises_id : null,
                participantUserIds: $userIds,
                ignoreAppointmentId: (int) $appointment->id,
                allowConflicts: (bool) ($data['allow_conflicts'] ?? false),
                preventPremisesOverlap: (bool) ($data['prevent_premises_overlap'] ?? config('workcore.scheduling.conflicts.prevent_premises_overlap_by_default', false)),
            );

            $fromStatus = (string) $appointment->status;
            $toStatus = in_array($fromStatus, ['cancelled', 'no_show'], true) ? 'scheduled' : $fromStatus;
            if ($toStatus !== $fromStatus) {
                $this->statusPolicy->assertTransition($fromStatus, $toStatus, $data['reason'] ?? null);
            }

            $this->db->table(self::APPOINTMENTS)->where('id', $appointment->id)->update([
                'starts_at' => $window['starts_at']->format('Y-m-d H:i:s'),
                'ends_at' => $window['ends_at']->format('Y-m-d H:i:s'),
                'timezone' => $window['timezone'],
                'status' => $toStatus,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);
            if (array_key_exists('assets', $data)) {
                $this->db->table('tz_appointment_assets')
                    ->where('company_id', $companyId)
                    ->where('appointment_id', $appointment->id)
                    ->delete();
                foreach ($assets as $asset) {
                    $this->insertAppointmentAsset($companyId, (int) $appointment->id, $asset);
                }
            }
            if ($toStatus !== $fromStatus) {
                $this->appendStatusHistory($companyId, (int) $appointment->id, $fromStatus, $toStatus, $data['reason'] ?? null, $actorId);
            }

            return $this->findByPublicId($companyId, $appointmentPublicId) ?? ['public_id' => $appointmentPublicId];
        }, 3);
    }

    public function changeStatus(string $appointmentPublicId, string $toStatus, ?string $reason, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($appointmentPublicId, $toStatus, $reason, $companyId, $actorId): array {
            $appointment = $this->db->table(self::APPOINTMENTS)
                ->where('company_id', $companyId)
                ->where('public_id', $appointmentPublicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'status']);
            if (! $appointment) {
                throw new InvalidArgumentException('Appointment does not belong to the active company.');
            }

            $fromStatus = (string) $appointment->status;
            $this->statusPolicy->assertTransition($fromStatus, $toStatus, $reason);
            if ($fromStatus !== $toStatus) {
                $this->db->table(self::APPOINTMENTS)->where('id', $appointment->id)->update([
                    'status' => $toStatus,
                    'updated_by_user_id' => $actorId,
                    'updated_at' => now(),
                ]);
                $this->appendStatusHistory($companyId, (int) $appointment->id, $fromStatus, $toStatus, $reason, $actorId);
            }

            return $this->findByPublicId($companyId, $appointmentPublicId) ?? ['public_id' => $appointmentPublicId];
        }, 3);
    }

    public function findByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->baseQuery($companyId)
            ->where('appointments.public_id', $publicId)
            ->first($this->summaryColumns());
        if (! $row) {
            return null;
        }

        $appointmentId = (int) $row->internal_id;
        $result = (array) $row;
        unset($result['internal_id']);
        $result['participants'] = $this->db->table('tz_appointment_participants as participants')
            ->leftJoin('tz_customer_contacts as contacts', 'contacts.id', '=', 'participants.contact_id')
            ->where('participants.company_id', $companyId)
            ->where('participants.appointment_id', $appointmentId)
            ->orderBy('participants.id')
            ->get([
                'participants.public_id', 'participants.user_id', 'contacts.public_id as contact_public_id',
                'participants.display_name', 'participants.email', 'participants.phone', 'participants.role',
                'participants.response_status', 'participants.is_required',
            ])->map(static fn ($item): array => (array) $item)->all();
        $result['assets'] = $this->db->table('tz_appointment_assets as appointment_assets')
            ->join('tz_assets as assets', 'assets.id', '=', 'appointment_assets.asset_id')
            ->where('appointment_assets.company_id', $companyId)
            ->where('appointment_assets.appointment_id', $appointmentId)
            ->orderBy('appointment_assets.id')
            ->get([
                'appointment_assets.public_id', 'assets.public_id as asset_public_id', 'assets.name as asset_name',
                'assets.asset_number', 'assets.status as asset_status', 'appointment_assets.role',
                'appointment_assets.is_required', 'appointment_assets.notes',
            ])->map(static fn ($item): array => (array) $item)->all();
        $result['status_history'] = $this->db->table('tz_appointment_status_history')
            ->where('company_id', $companyId)
            ->where('appointment_id', $appointmentId)
            ->orderBy('changed_at')
            ->get(['public_id', 'from_status', 'to_status', 'reason', 'changed_by_user_id', 'changed_at'])
            ->map(static fn ($item): array => (array) $item)->all();

        return $result;
    }

    /** @param array<string,mixed> $data @return array{work_order_id:?int,customer_id:?int,premises_id:?int} */
    private function resolveRelationships(array $data, int $companyId): array
    {
        $workOrder = null;
        if (! empty($data['work_order_public_id'])) {
            $workOrder = $this->db->table('tz_work_orders')
                ->where('company_id', $companyId)
                ->where('public_id', (string) $data['work_order_public_id'])
                ->whereNull('deleted_at')
                ->first(['id', 'customer_id', 'premises_id']);
            if (! $workOrder) {
                throw new InvalidArgumentException('Work order does not belong to the active company.');
            }
        }

        $customer = null;
        if (! empty($data['customer_public_id'])) {
            $customer = $this->db->table('tz_customers')
                ->where('company_id', $companyId)
                ->where('public_id', (string) $data['customer_public_id'])
                ->whereNull('deleted_at')
                ->first(['id']);
            if (! $customer) {
                throw new InvalidArgumentException('Customer does not belong to the active company.');
            }
        }

        $premises = null;
        if (! empty($data['premises_public_id'])) {
            $premises = $this->db->table('tz_premises')
                ->where('company_id', $companyId)
                ->where('public_id', (string) $data['premises_public_id'])
                ->whereNull('deleted_at')
                ->first(['id', 'customer_id']);
            if (! $premises) {
                throw new InvalidArgumentException('Premises does not belong to the active company.');
            }
        }

        $customerId = $workOrder?->customer_id ?? $customer?->id ?? $premises?->customer_id;
        $premisesId = ($workOrder && $workOrder->premises_id) ? $workOrder->premises_id : $premises?->id;
        if ($workOrder && $customer && (int) $workOrder->customer_id !== (int) $customer->id) {
            throw new InvalidArgumentException('Appointment customer does not match the selected work order.');
        }
        if ($workOrder && $workOrder->premises_id && $premises && (int) $workOrder->premises_id !== (int) $premises->id) {
            throw new InvalidArgumentException('Appointment premises does not match the selected work order.');
        }
        if ($premises && $customer && (int) $premises->customer_id !== (int) $customer->id) {
            throw new InvalidArgumentException('Premises does not belong to the selected customer.');
        }
        if (! $workOrder && ! $customerId && ! $premisesId) {
            throw new InvalidArgumentException('An appointment must relate to a work order, customer, or premises.');
        }

        return [
            'work_order_id' => $workOrder ? (int) $workOrder->id : null,
            'customer_id' => $customerId ? (int) $customerId : null,
            'premises_id' => $premisesId ? (int) $premisesId : null,
        ];
    }

    /** @param array<int,array<string,mixed>> $participants @return array<int,array<string,mixed>> */
    private function normaliseParticipants(array $participants, int $companyId, ?int $customerId): array
    {
        $normalised = [];
        $seen = [];
        foreach ($participants as $participant) {
            $participant = (array) $participant;
            $userId = isset($participant['user_id']) ? (int) $participant['user_id'] : null;
            $contact = null;
            if ($userId) {
                $active = $this->db->table('tz_company_memberships')
                    ->where('company_id', $companyId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')->exists();
                if (! $active) {
                    throw new InvalidArgumentException('Appointment participant is not an active company member.');
                }
            }
            if (! empty($participant['contact_public_id'])) {
                $contactQuery = $this->db->table('tz_customer_contacts')
                    ->where('company_id', $companyId)
                    ->where('public_id', (string) $participant['contact_public_id'])
                    ->whereNull('deleted_at');
                if ($customerId) {
                    $contactQuery->where('customer_id', $customerId);
                }
                $contact = $contactQuery->first(['id', 'name', 'email', 'phone']);
                if (! $contact) {
                    throw new InvalidArgumentException('Appointment contact does not belong to the selected company and customer.');
                }
            }
            $displayName = trim((string) ($participant['display_name'] ?? $contact?->name ?? ''));
            if (! $userId && ! $contact && $displayName === '') {
                throw new InvalidArgumentException('Each appointment participant requires a company user, customer contact, or display name.');
            }
            $identity = $userId ? "user:{$userId}" : ($contact ? "contact:{$contact->id}" : 'external:' . strtolower($displayName . '|' . ($participant['email'] ?? '')));
            if (isset($seen[$identity])) {
                throw new InvalidArgumentException('Duplicate appointment participant supplied.');
            }
            $seen[$identity] = true;
            $normalised[] = [
                'user_id' => $userId,
                'contact_id' => $contact ? (int) $contact->id : null,
                'display_name' => $displayName !== '' ? $displayName : null,
                'email' => $participant['email'] ?? $contact?->email,
                'phone' => $participant['phone'] ?? $contact?->phone,
                'role' => $participant['role'] ?? ($userId ? 'assignee' : 'attendee'),
                'response_status' => $participant['response_status'] ?? 'needs_action',
                'is_required' => (bool) ($participant['is_required'] ?? false),
            ];
        }

        return $normalised;
    }

    /** @param array<string,mixed> $participant */
    private function insertParticipant(int $companyId, int $appointmentId, array $participant): void
    {
        $this->db->table('tz_appointment_participants')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'appointment_id' => $appointmentId,
            'user_id' => $participant['user_id'],
            'contact_id' => $participant['contact_id'],
            'display_name' => $participant['display_name'],
            'email' => $participant['email'],
            'phone' => $participant['phone'],
            'role' => $participant['role'],
            'response_status' => $participant['response_status'],
            'is_required' => $participant['is_required'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $asset */
    private function insertAppointmentAsset(int $companyId, int $appointmentId, array $asset): void
    {
        $this->db->table('tz_appointment_assets')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'appointment_id' => $appointmentId,
            'asset_id' => $asset['asset_id'],
            'role' => $asset['role'],
            'is_required' => $asset['is_required'],
            'notes' => $asset['notes'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<int,int> $participantUserIds */
    private function assertNoConflicts(
        int $companyId,
        string $startsAt,
        string $endsAt,
        ?int $premisesId,
        array $participantUserIds,
        ?int $ignoreAppointmentId,
        bool $allowConflicts,
        bool $preventPremisesOverlap,
    ): void {
        if ($allowConflicts) {
            return;
        }

        $conflicts = [];
        if ($participantUserIds !== []) {
            $query = $this->db->table('tz_appointment_participants as participants')
                ->join(self::APPOINTMENTS . ' as appointments', 'appointments.id', '=', 'participants.appointment_id')
                ->where('appointments.company_id', $companyId)
                ->whereIn('appointments.status', self::ACTIVE_STATUSES)
                ->whereIn('participants.user_id', $participantUserIds)
                ->where('appointments.starts_at', '<', $endsAt)
                ->where('appointments.ends_at', '>', $startsAt)
                ->whereNull('appointments.deleted_at');
            if ($ignoreAppointmentId) {
                $query->where('appointments.id', '!=', $ignoreAppointmentId);
            }
            $conflicts = $query->pluck('appointments.public_id')->all();
        }

        if ($preventPremisesOverlap && $premisesId) {
            $query = $this->db->table(self::APPOINTMENTS)
                ->where('company_id', $companyId)
                ->where('premises_id', $premisesId)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->whereNull('deleted_at');
            if ($ignoreAppointmentId) {
                $query->where('id', '!=', $ignoreAppointmentId);
            }
            $conflicts = array_merge($conflicts, $query->pluck('public_id')->all());
        }

        $conflicts = array_values(array_unique(array_map('strval', $conflicts)));
        if ($conflicts !== []) {
            throw new InvalidArgumentException('Appointment conflicts with existing appointment(s): ' . implode(', ', $conflicts));
        }
    }

    private function appendStatusHistory(int $companyId, int $appointmentId, ?string $from, string $to, ?string $reason, int $actorId): void
    {
        $this->db->table('tz_appointment_status_history')->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'appointment_id' => $appointmentId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by_user_id' => $actorId,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function baseQuery(int $companyId): Builder
    {
        return $this->db->table(self::APPOINTMENTS . ' as appointments')
            ->leftJoin('tz_work_orders as work_orders', function ($join): void {
                $join->on('work_orders.id', '=', 'appointments.work_order_id')
                    ->on('work_orders.company_id', '=', 'appointments.company_id')
                    ->whereNull('work_orders.deleted_at');
            })
            ->leftJoin('tz_customers as customers', function ($join): void {
                $join->on('customers.id', '=', 'appointments.customer_id')
                    ->on('customers.company_id', '=', 'appointments.company_id')
                    ->whereNull('customers.deleted_at');
            })
            ->leftJoin('tz_premises as premises', function ($join): void {
                $join->on('premises.id', '=', 'appointments.premises_id')
                    ->on('premises.company_id', '=', 'appointments.company_id')
                    ->whereNull('premises.deleted_at');
            })
            ->where('appointments.company_id', $companyId)
            ->whereNull('appointments.deleted_at');
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }

    /** @return array<int,string> */
    private function summaryColumns(): array
    {
        return [
            'appointments.id as internal_id', 'appointments.public_id',
            'work_orders.public_id as work_order_public_id', 'work_orders.number as work_order_number',
            'customers.public_id as customer_public_id', 'customers.name as customer_name',
            'premises.public_id as premises_public_id', 'premises.name as premises_name',
            'appointments.title', 'appointments.description', 'appointments.appointment_type', 'appointments.status',
            'appointments.starts_at', 'appointments.ends_at', 'appointments.timezone', 'appointments.all_day',
            'appointments.access_notes', 'appointments.internal_notes', 'appointments.source',
            'appointments.created_at', 'appointments.updated_at',
        ];
    }
}
