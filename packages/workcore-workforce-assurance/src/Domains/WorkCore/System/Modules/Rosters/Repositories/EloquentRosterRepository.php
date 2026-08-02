<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\Modules\Rosters\Services\AvailabilityRulePolicy;
use App\Domains\WorkCore\System\Modules\Rosters\Services\RosterStatusPolicy;
use App\Domains\WorkCore\System\Modules\Rosters\Services\RosterWindowPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class EloquentRosterRepository extends TenantScopedRepository implements RosterRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private RosterWindowPolicy $windowPolicy,
        private RosterStatusPolicy $statusPolicy,
        private AvailabilityRulePolicy $availabilityPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function searchRosters(int $companyId, ?string $query = null, ?string $status = null, ?string $rosterType = null, ?string $premisesPublicId = null, ?string $startsFrom = null, ?string $startsTo = null, int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $builder = $this->baseRosterQuery($companyId);
        $builder->when($query, static fn (Builder $q) => $q->where(static function (Builder $nested) use ($query): void {
            $nested->where('rosters.name', 'like', "%{$query}%")
                ->orWhere('rosters.notes', 'like', "%{$query}%")
                ->orWhere('premises.name', 'like', "%{$query}%");
        }));
        $builder->when($status, static fn (Builder $q) => $q->where('rosters.status', $status));
        $builder->when($rosterType, static fn (Builder $q) => $q->where('rosters.roster_type', $rosterType));
        $builder->when($premisesPublicId, static fn (Builder $q) => $q->where('premises.public_id', $premisesPublicId));
        $builder->when($startsFrom, static fn (Builder $q) => $q->where('rosters.ends_on', '>=', $startsFrom));
        $builder->when($startsTo, static fn (Builder $q) => $q->where('rosters.starts_on', '<=', $startsTo));

        return $builder->select($this->rosterSummaryColumns())
            ->orderByDesc('rosters.starts_on')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function searchAvailability(int $companyId, ?string $workerPublicId = null, ?string $availabilityType = null, ?int $weekday = null, int $perPage = 50): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $builder = $this->db->table('tz_worker_availability_rules as rules')
            ->join('tz_workers as workers', function ($join): void {
                $join->on('workers.id', '=', 'rules.worker_id')
                    ->on('workers.company_id', '=', 'rules.company_id')
                    ->whereNull('workers.deleted_at');
            })
            ->where('rules.company_id', $companyId)
            ->whereNull('rules.deleted_at');
        $builder->when($workerPublicId, static fn (Builder $q) => $q->where('workers.public_id', $workerPublicId));
        $builder->when($availabilityType, static fn (Builder $q) => $q->where('rules.availability_type', $availabilityType));
        $builder->when($weekday, static fn (Builder $q) => $q->where('rules.weekday', $weekday));

        return $builder->select([
            'rules.public_id', 'workers.public_id as worker_public_id', 'workers.display_name as worker_name',
            'rules.availability_type', 'rules.recurrence_type', 'rules.weekday', 'rules.starts_at', 'rules.ends_at',
            'rules.effective_from', 'rules.effective_to', 'rules.timezone', 'rules.source', 'rules.notes', 'rules.created_at', 'rules.updated_at',
        ])->orderBy('workers.display_name')->orderBy('rules.weekday')->orderBy('rules.starts_at')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function createAvailabilityRule(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $worker = $this->workerByPublicId($companyId, (string) $data['worker_public_id']);
        $type = (string) ($data['availability_type'] ?? 'available');
        if (! in_array($type, ['available', 'unavailable', 'preferred', 'on_call'], true)) {
            throw new InvalidArgumentException('Unsupported availability type.');
        }
        $recurrence = (string) ($data['recurrence_type'] ?? 'weekly');
        if (! in_array($recurrence, ['weekly', 'date_range', 'one_off'], true)) {
            throw new InvalidArgumentException('Unsupported availability recurrence type.');
        }
        $weekday = isset($data['weekday']) ? (int) $data['weekday'] : null;
        if ($recurrence === 'weekly' && ($weekday === null || $weekday < 1 || $weekday > 7)) {
            throw new InvalidArgumentException('Weekly availability requires a weekday from 1 to 7.');
        }
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        if (($startsAt === null) !== ($endsAt === null)) {
            throw new InvalidArgumentException('Availability start and end times must be supplied together.');
        }
        if ($startsAt !== null && (string) $endsAt <= (string) $startsAt) {
            throw new InvalidArgumentException('Availability end time must be after start time.');
        }
        if (! empty($data['effective_from']) && ! empty($data['effective_to']) && (string) $data['effective_to'] < (string) $data['effective_from']) {
            throw new InvalidArgumentException('Availability effective end cannot precede its start.');
        }

        $publicId = (string) Str::ulid();
        $this->db->table('tz_worker_availability_rules')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'worker_id' => (int) $worker->id,
            'availability_type' => $type,
            'recurrence_type' => $recurrence,
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'timezone' => (string) ($data['timezone'] ?? config('workcore.context.default_timezone', 'Australia/Melbourne')),
            'source' => (string) ($data['source'] ?? 'manual'),
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = $this->db->table('tz_worker_availability_rules as rules')
            ->join('tz_workers as workers', function ($join): void {
                $join->on('workers.id', '=', 'rules.worker_id')
                    ->on('workers.company_id', '=', 'rules.company_id');
            })
            ->where('rules.company_id', $companyId)
            ->where('rules.public_id', $publicId)
            ->first([
                'rules.public_id', 'workers.public_id as worker_public_id', 'workers.display_name as worker_name',
                'rules.availability_type', 'rules.recurrence_type', 'rules.weekday', 'rules.starts_at', 'rules.ends_at',
                'rules.effective_from', 'rules.effective_to', 'rules.timezone', 'rules.source', 'rules.notes',
                'rules.created_at', 'rules.updated_at',
            ]);

        return $record ? (array) $record : ['public_id' => $publicId];
    }

    public function createRoster(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $startsOn = (string) $data['starts_on'];
        $endsOn = (string) $data['ends_on'];
        if ($endsOn < $startsOn) {
            throw new InvalidArgumentException('Roster end date cannot precede its start date.');
        }
        $premisesId = $this->resolveOptionalPremises($companyId, $data['premises_public_id'] ?? null);
        $publicId = (string) Str::ulid();
        $rosterId = $this->db->table('tz_rosters')->insertGetId([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'premises_id' => $premisesId,
            'name' => (string) $data['name'],
            'roster_type' => (string) ($data['roster_type'] ?? 'operations'),
            'status' => 'draft',
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'timezone' => (string) ($data['timezone'] ?? config('workcore.context.default_timezone', 'Australia/Melbourne')),
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->appendRosterHistory($companyId, (int) $rosterId, null, 'draft', 'Roster created.', $actorId);

        return $this->findRosterByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
    }

    public function createShift(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $roster = $this->rosterByPublicId($companyId, (string) $data['roster_public_id']);
        if ((string) $roster->status !== 'draft') {
            throw new InvalidArgumentException('New shifts may only be added to a draft roster.');
        }
        $timezone = (string) ($data['timezone'] ?? $roster->timezone);
        $window = $this->windowPolicy->normalise((string) $data['starts_at'], (string) $data['ends_at'], $timezone);
        $localStart = $window['starts_at']->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
        $localEnd = $window['ends_at']->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
        if ($localStart < (string) $roster->starts_on || $localEnd > (string) $roster->ends_on) {
            throw new InvalidArgumentException('Roster shift must fall within the roster date range.');
        }
        $premisesId = $this->resolveOptionalPremises($companyId, $data['premises_public_id'] ?? null) ?? ($roster->premises_id ? (int) $roster->premises_id : null);
        $workOrderId = $this->resolveOptionalRecord('tz_work_orders', $companyId, $data['work_order_public_id'] ?? null, 'Work order');
        $appointmentId = $this->resolveOptionalRecord('tz_appointments', $companyId, $data['appointment_public_id'] ?? null, 'Appointment');
        $requiredSkills = $this->normaliseRequiredSkills($companyId, (array) ($data['required_skill_public_ids'] ?? []));
        $allowConflicts = (bool) ($data['allow_conflicts'] ?? false);
        if ($allowConflicts && trim((string) ($data['conflict_override_reason'] ?? '')) === '') {
            throw new InvalidArgumentException('A conflict override requires a reason.');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $roster, $window, $timezone, $premisesId, $workOrderId, $appointmentId, $requiredSkills, $allowConflicts): array {
            $publicId = (string) Str::ulid();
            $shiftId = $this->db->table('tz_roster_shifts')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'roster_id' => (int) $roster->id,
                'premises_id' => $premisesId,
                'work_order_id' => $workOrderId,
                'appointment_id' => $appointmentId,
                'title' => (string) $data['title'],
                'shift_type' => (string) ($data['shift_type'] ?? 'operations'),
                'status' => 'draft',
                'starts_at' => $window['starts_at']->format('Y-m-d H:i:s'),
                'ends_at' => $window['ends_at']->format('Y-m-d H:i:s'),
                'timezone' => $timezone,
                'required_worker_count' => max(1, (int) ($data['required_worker_count'] ?? 1)),
                'required_skill_public_ids' => $requiredSkills === [] ? null : json_encode($requiredSkills, JSON_THROW_ON_ERROR),
                'instructions' => $data['instructions'] ?? null,
                'allow_conflicts' => $allowConflicts,
                'conflict_override_reason' => $data['conflict_override_reason'] ?? null,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! empty($data['worker_public_id'])) {
                $this->assignShiftByInternalId((int) $shiftId, (string) $data['worker_public_id'], (array) $data, $companyId, $actorId);
            }
            return $this->shiftByPublicId($companyId, $publicId);
        }, 3);
    }

    public function assignShift(string $shiftPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $shift = $this->db->table('tz_roster_shifts')->where('company_id', $companyId)->where('public_id', $shiftPublicId)->whereNull('deleted_at')->first();
        if (! $shift) {
            throw new InvalidArgumentException('Roster shift does not belong to the active company.');
        }
        $this->assignShiftByInternalId((int) $shift->id, (string) $data['worker_public_id'], $data, $companyId, $actorId);
        return $this->shiftByPublicId($companyId, $shiftPublicId);
    }

    public function publishRoster(string $rosterPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($rosterPublicId, $data, $companyId, $actorId): array {
            $roster = $this->db->table('tz_rosters')->where('company_id', $companyId)->where('public_id', $rosterPublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $roster) {
                throw new InvalidArgumentException('Roster does not belong to the active company.');
            }
            $toStatus = (string) ($data['status'] ?? 'published');
            $reason = $data['reason'] ?? null;
            $this->statusPolicy->assertTransition((string) $roster->status, $toStatus, $reason);

            if ($toStatus === 'published') {
                $gaps = $this->db->table('tz_roster_shifts as shifts')
                    ->leftJoin('tz_roster_shift_assignments as assignments', function ($join): void {
                        $join->on('assignments.roster_shift_id', '=', 'shifts.id')
                            ->on('assignments.company_id', '=', 'shifts.company_id')
                            ->whereIn('assignments.status', ['assigned', 'accepted']);
                    })
                    ->where('shifts.company_id', $companyId)
                    ->where('shifts.roster_id', $roster->id)
                    ->whereNull('shifts.deleted_at')
                    ->selectRaw('shifts.id, shifts.required_worker_count, COUNT(assignments.id) as assigned_count')
                    ->groupBy('shifts.id', 'shifts.required_worker_count')
                    ->havingRaw('COUNT(assignments.id) < shifts.required_worker_count')
                    ->get()
                    ->count();
                if ($gaps > 0 && ! (bool) ($data['allow_gaps'] ?? false)) {
                    throw new InvalidArgumentException("Roster has {$gaps} uncovered shift(s). Supply allow_gaps with a reason to publish deliberately.");
                }
                if ($gaps > 0 && trim((string) $reason) === '') {
                    throw new InvalidArgumentException('Publishing a roster with coverage gaps requires a reason.');
                }
            }

            $this->db->table('tz_rosters')->where('id', $roster->id)->update([
                'status' => $toStatus,
                'published_at' => $toStatus === 'published' ? now() : ($toStatus === 'draft' ? null : $roster->published_at),
                'closed_at' => $toStatus === 'closed' ? now() : null,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);
            if (in_array($toStatus, ['published', 'cancelled', 'draft'], true)) {
                $this->db->table('tz_roster_shifts')->where('roster_id', $roster->id)->where('company_id', $companyId)->whereNull('deleted_at')->update([
                    'status' => $toStatus,
                    'updated_by_user_id' => $actorId,
                    'updated_at' => now(),
                ]);
            }
            $this->appendRosterHistory($companyId, (int) $roster->id, (string) $roster->status, $toStatus, $reason, $actorId);
            return $this->findRosterByPublicId($companyId, $rosterPublicId) ?? ['public_id' => $rosterPublicId];
        }, 3);
    }

    public function findRosterByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->baseRosterQuery($companyId)->where('rosters.public_id', $publicId)->first($this->rosterSummaryColumns());
        if (! $row) {
            return null;
        }
        $result = (array) $row;
        $rosterId = (int) $result['internal_id'];
        unset($result['internal_id']);
        $result['shifts'] = $this->db->table('tz_roster_shifts as shifts')
            ->leftJoin('tz_premises as premises', 'premises.id', '=', 'shifts.premises_id')
            ->where('shifts.company_id', $companyId)->where('shifts.roster_id', $rosterId)->whereNull('shifts.deleted_at')
            ->orderBy('shifts.starts_at')
            ->get(['shifts.id as internal_id', 'shifts.public_id', 'premises.public_id as premises_public_id', 'premises.name as premises_name', 'shifts.title', 'shifts.shift_type', 'shifts.status', 'shifts.starts_at', 'shifts.ends_at', 'shifts.timezone', 'shifts.required_worker_count', 'shifts.required_skill_public_ids', 'shifts.instructions'])
            ->map(function ($shift) use ($companyId): array {
                $item = (array) $shift;
                $shiftId = (int) $item['internal_id'];
                unset($item['internal_id']);
                $item['required_skill_public_ids'] = $item['required_skill_public_ids'] ? json_decode((string) $item['required_skill_public_ids'], true) : [];
                $item['assignments'] = $this->db->table('tz_roster_shift_assignments as assignments')
                    ->join('tz_workers as workers', 'workers.id', '=', 'assignments.worker_id')
                    ->where('assignments.company_id', $companyId)->where('assignments.roster_shift_id', $shiftId)
                    ->get(['assignments.public_id', 'workers.public_id as worker_public_id', 'workers.display_name as worker_name', 'workers.job_title', 'assignments.assignment_role', 'assignments.status', 'assignments.assigned_at', 'assignments.responded_at'])
                    ->map(static fn ($assignment): array => (array) $assignment)->all();
                return $item;
            })->all();
        $result['status_history'] = $this->db->table('tz_roster_status_history')->where('company_id', $companyId)->where('roster_id', $rosterId)->orderBy('changed_at')->get(['public_id', 'from_status', 'to_status', 'reason', 'changed_by_user_id', 'changed_at'])->map(static fn ($history): array => (array) $history)->all();
        return $result;
    }

    private function assignShiftByInternalId(int $shiftId, string $workerPublicId, array $data, int $companyId, int $actorId): void
    {
        $shift = $this->db->table('tz_roster_shifts')->where('company_id', $companyId)->where('id', $shiftId)->whereNull('deleted_at')->first();
        if (! $shift) {
            throw new InvalidArgumentException('Roster shift does not belong to the active company.');
        }
        $worker = $this->workerByPublicId($companyId, $workerPublicId);
        $allowConflicts = (bool) ($data['allow_conflicts'] ?? $shift->allow_conflicts ?? false);
        $overrideReason = trim((string) ($data['conflict_override_reason'] ?? $shift->conflict_override_reason ?? ''));
        $conflicts = $this->workerConflicts($companyId, (int) $worker->id, (string) $shift->starts_at, (string) $shift->ends_at, $shiftId, (array) json_decode((string) ($shift->required_skill_public_ids ?? '[]'), true));
        if ($conflicts !== [] && ! $allowConflicts) {
            throw new InvalidArgumentException(implode(' ', $conflicts));
        }
        if ($conflicts !== [] && $overrideReason === '') {
            throw new InvalidArgumentException('Overriding worker qualification or scheduling conflicts requires a reason.');
        }
        $existingAssignmentId = $this->db->table('tz_roster_shift_assignments')
            ->where('roster_shift_id', $shiftId)
            ->where('worker_id', (int) $worker->id)
            ->value('id');
        $assignmentValues = [
            'company_id' => $companyId,
            'assignment_role' => (string) ($data['assignment_role'] ?? 'worker'),
            'status' => (string) ($data['status'] ?? 'assigned'),
            'assigned_at' => now(),
            'assigned_by_user_id' => $actorId,
            'notes' => $data['notes'] ?? ($conflicts !== [] ? $overrideReason : null),
            'updated_at' => now(),
        ];
        if ($existingAssignmentId) {
            $this->db->table('tz_roster_shift_assignments')->where('id', $existingAssignmentId)->update($assignmentValues);
        } else {
            $this->db->table('tz_roster_shift_assignments')->insert([
                'public_id' => (string) Str::ulid(),
                'roster_shift_id' => $shiftId,
                'worker_id' => (int) $worker->id,
                'created_at' => now(),
            ] + $assignmentValues);
        }
    }

    /** @param list<string> $requiredSkills @return list<string> */
    private function workerConflicts(int $companyId, int $workerId, string $startsAt, string $endsAt, int $ignoreShiftId, array $requiredSkills): array
    {
        $conflicts = [];
        $worker = $this->db->table('tz_workers')->where('company_id', $companyId)->where('id', $workerId)->whereNull('deleted_at')->first(['employment_status', 'timezone']);
        if (! $worker || (string) $worker->employment_status !== 'active') {
            $conflicts[] = 'Worker is not operationally active.';
            return $conflicts;
        }
        $overlap = $this->db->table('tz_roster_shift_assignments as assignments')
            ->join('tz_roster_shifts as shifts', 'shifts.id', '=', 'assignments.roster_shift_id')
            ->where('assignments.company_id', $companyId)->where('assignments.worker_id', $workerId)
            ->where('shifts.id', '<>', $ignoreShiftId)->whereNull('shifts.deleted_at')
            ->whereIn('shifts.status', ['draft', 'published', 'accepted', 'in_progress'])
            ->where('shifts.starts_at', '<', $endsAt)->where('shifts.ends_at', '>', $startsAt)->exists();
        if ($overlap) {
            $conflicts[] = 'Worker already has an overlapping roster shift.';
        }
        if ((bool) config('workcore.attendance.rules.prevent_roster_assignment_during_approved_leave', true)) {
            $leaveConflict = $this->db->table('tz_leave_requests')->where('company_id', $companyId)->where('worker_id', $workerId)
                ->where('status', 'approved')->whereNull('deleted_at')->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
            if ($leaveConflict) {
                $conflicts[] = 'Worker has approved leave during this roster shift.';
            }
        }
        $userId = $this->db->table('tz_workers')->where('id', $workerId)->value('user_id');
        if ($userId) {
            $appointmentConflict = $this->db->table('tz_appointment_participants as participants')
                ->join('tz_appointments as appointments', 'appointments.id', '=', 'participants.appointment_id')
                ->where('participants.company_id', $companyId)->where('participants.user_id', $userId)
                ->whereIn('appointments.status', ['tentative', 'scheduled', 'confirmed', 'in_progress'])
                ->where('appointments.starts_at', '<', $endsAt)->where('appointments.ends_at', '>', $startsAt)->exists();
            if ($appointmentConflict) {
                $conflicts[] = 'Worker has an overlapping appointment.';
            }
            $dispatchConflict = $this->db->table('tz_dispatch_assignments')->where('company_id', $companyId)->where('assigned_user_id', $userId)
                ->whereIn('status', ['assigned', 'dispatched', 'en_route', 'arrived', 'in_progress'])
                ->where('scheduled_start', '<', $endsAt)->where('scheduled_end', '>', $startsAt)->exists();
            if ($dispatchConflict) {
                $conflicts[] = 'Worker has an overlapping dispatch assignment.';
            }
        }
        $zone = new DateTimeZone((string) ($worker->timezone ?: config('workcore.context.default_timezone', 'Australia/Melbourne')));
        $localStart = (new DateTimeImmutable($startsAt, new DateTimeZone('UTC')))->setTimezone($zone);
        $localEnd = (new DateTimeImmutable($endsAt, new DateTimeZone('UTC')))->setTimezone($zone);
        $rules = $this->db->table('tz_worker_availability_rules')->where('company_id', $companyId)->where('worker_id', $workerId)->whereNull('deleted_at')
            ->where(static function (Builder $query) use ($localStart): void {
                $date = $localStart->format('Y-m-d');
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })->where(static function (Builder $query) use ($localEnd): void {
                $date = $localEnd->format('Y-m-d');
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })->get(['availability_type', 'weekday', 'starts_at', 'ends_at'])->map(static fn ($rule): array => (array) $rule)->all();
        if ($rules !== []) {
            $availability = $this->availabilityPolicy->evaluate($localStart, $localEnd, $rules);
            $conflicts = array_merge($conflicts, $availability['conflicts']);
        }
        if ($requiredSkills !== []) {
            $qualified = $this->db->table('tz_worker_skills as worker_skills')
                ->join('tz_skills as skills', 'skills.id', '=', 'worker_skills.skill_id')
                ->where('worker_skills.company_id', $companyId)->where('worker_skills.worker_id', $workerId)
                ->where('worker_skills.verified', true)->whereIn('skills.public_id', $requiredSkills)
                ->distinct()->count('skills.public_id');
            if ($qualified < count(array_unique($requiredSkills))) {
                $conflicts[] = 'Worker does not hold every required verified skill.';
            }
        }
        return array_values(array_unique($conflicts));
    }

    private function shiftByPublicId(int $companyId, string $publicId): array
    {
        $shift = $this->db->table('tz_roster_shifts as shifts')->leftJoin('tz_rosters as rosters', 'rosters.id', '=', 'shifts.roster_id')->leftJoin('tz_premises as premises', 'premises.id', '=', 'shifts.premises_id')
            ->where('shifts.company_id', $companyId)->where('shifts.public_id', $publicId)->whereNull('shifts.deleted_at')
            ->first(['shifts.public_id', 'rosters.public_id as roster_public_id', 'premises.public_id as premises_public_id', 'premises.name as premises_name', 'shifts.title', 'shifts.shift_type', 'shifts.status', 'shifts.starts_at', 'shifts.ends_at', 'shifts.timezone', 'shifts.required_worker_count', 'shifts.required_skill_public_ids', 'shifts.instructions', 'shifts.created_at', 'shifts.updated_at']);
        if (! $shift) {
            throw new InvalidArgumentException('Roster shift does not belong to the active company.');
        }
        $result = (array) $shift;
        $result['required_skill_public_ids'] = $result['required_skill_public_ids'] ? json_decode((string) $result['required_skill_public_ids'], true) : [];
        return $result;
    }

    private function rosterByPublicId(int $companyId, string $publicId): object
    {
        $roster = $this->db->table('tz_rosters')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first();
        if (! $roster) {
            throw new InvalidArgumentException('Roster does not belong to the active company.');
        }
        return $roster;
    }

    private function workerByPublicId(int $companyId, string $publicId): object
    {
        $worker = $this->db->table('tz_workers')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id', 'user_id', 'employment_status', 'timezone']);
        if (! $worker) {
            throw new InvalidArgumentException('Worker does not belong to the active company.');
        }
        return $worker;
    }

    private function resolveOptionalPremises(int $companyId, mixed $publicId): ?int
    {
        return $this->resolveOptionalRecord('tz_premises', $companyId, $publicId, 'Premises');
    }

    private function resolveOptionalRecord(string $table, int $companyId, mixed $publicId, string $label): ?int
    {
        if (! $publicId) {
            return null;
        }
        $id = $this->db->table($table)->where('company_id', $companyId)->where('public_id', (string) $publicId)->whereNull('deleted_at')->value('id');
        if (! $id) {
            throw new InvalidArgumentException("{$label} does not belong to the active company.");
        }
        return (int) $id;
    }

    /** @param list<string> $publicIds @return list<string> */
    private function normaliseRequiredSkills(int $companyId, array $publicIds): array
    {
        $publicIds = array_values(array_unique(array_filter(array_map('strval', $publicIds))));
        if ($publicIds === []) {
            return [];
        }
        $found = $this->db->table('tz_skills')->where('company_id', $companyId)->whereIn('public_id', $publicIds)->where('active', true)->whereNull('deleted_at')->pluck('public_id')->map(static fn ($value): string => (string) $value)->all();
        if (count($found) !== count($publicIds)) {
            throw new InvalidArgumentException('One or more required skills do not belong to the active company.');
        }
        return $publicIds;
    }

    private function appendRosterHistory(int $companyId, int $rosterId, ?string $from, string $to, ?string $reason, int $actorId): void
    {
        $this->db->table('tz_roster_status_history')->insert([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'roster_id' => $rosterId,
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason,
            'changed_by_user_id' => $actorId, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function baseRosterQuery(int $companyId): Builder
    {
        return $this->db->table('tz_rosters as rosters')
            ->leftJoin('tz_premises as premises', function ($join): void {
                $join->on('premises.id', '=', 'rosters.premises_id')->on('premises.company_id', '=', 'rosters.company_id')->whereNull('premises.deleted_at');
            })
            ->where('rosters.company_id', $companyId)->whereNull('rosters.deleted_at');
    }

    /** @return list<string> */
    private function rosterSummaryColumns(): array
    {
        return ['rosters.id as internal_id', 'rosters.public_id', 'premises.public_id as premises_public_id', 'premises.name as premises_name', 'rosters.name', 'rosters.roster_type', 'rosters.status', 'rosters.starts_on', 'rosters.ends_on', 'rosters.timezone', 'rosters.notes', 'rosters.published_at', 'rosters.closed_at', 'rosters.created_at', 'rosters.updated_at'];
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }
}
