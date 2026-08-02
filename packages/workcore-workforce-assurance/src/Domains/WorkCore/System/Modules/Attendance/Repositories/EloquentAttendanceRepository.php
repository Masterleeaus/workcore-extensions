<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Attendance\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Attendance\Services\{AttendanceClockPolicy,LeaveRequestPolicy,TimesheetStatusPolicy};
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentAttendanceRepository extends TenantScopedRepository implements AttendanceRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private AttendanceClockPolicy $clockPolicy,
        private LeaveRequestPolicy $leavePolicy,
        private TimesheetStatusPolicy $timesheetPolicy,
    ) { parent::__construct($db, $tenant); }

    public function searchAttendance(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $q = $this->db->table('tz_attendance_sessions as sessions')
            ->join('tz_workers as workers', function ($join): void {
                $join->on('workers.id','=','sessions.worker_id')->on('workers.company_id','=','sessions.company_id');
            })
            ->leftJoin('tz_premises as premises','premises.id','=','sessions.premises_id')
            ->where('sessions.company_id',$companyId)->whereNull('sessions.deleted_at');
        foreach (['status'] as $field) if (!empty($filters[$field])) $q->where("sessions.{$field}",$filters[$field]);
        if (!empty($filters['worker_public_id'])) $q->where('workers.public_id',$filters['worker_public_id']);
        if (!empty($filters['premises_public_id'])) $q->where('premises.public_id',$filters['premises_public_id']);
        if (!empty($filters['from'])) $q->where('sessions.clocked_in_at','>=',$filters['from']);
        if (!empty($filters['to'])) $q->where('sessions.clocked_in_at','<=',$filters['to']);
        return $q->select(['sessions.*','workers.public_id as worker_public_id','workers.display_name as worker_name','premises.public_id as premises_public_id','premises.name as premises_name'])
            ->orderByDesc('sessions.clocked_in_at')->paginate(max(1,min($perPage,100)));
    }

    public function searchLeaveRequests(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $q = $this->db->table('tz_leave_requests as requests')
            ->join('tz_workers as workers', function ($join): void { $join->on('workers.id','=','requests.worker_id')->on('workers.company_id','=','requests.company_id'); })
            ->join('tz_leave_types as types', function ($join): void { $join->on('types.id','=','requests.leave_type_id')->on('types.company_id','=','requests.company_id'); })
            ->leftJoin('tz_workers as replacements','replacements.id','=','requests.replacement_worker_id')
            ->where('requests.company_id',$companyId)->whereNull('requests.deleted_at');
        if (!empty($filters['status'])) $q->where('requests.status',$filters['status']);
        if (!empty($filters['worker_public_id'])) $q->where('workers.public_id',$filters['worker_public_id']);
        if (!empty($filters['leave_type_public_id'])) $q->where('types.public_id',$filters['leave_type_public_id']);
        if (!empty($filters['from'])) $q->where('requests.ends_at','>=',$filters['from']);
        if (!empty($filters['to'])) $q->where('requests.starts_at','<=',$filters['to']);
        return $q->select(['requests.*','workers.public_id as worker_public_id','workers.display_name as worker_name','types.public_id as leave_type_public_id','types.name as leave_type_name','types.paid as leave_is_paid','replacements.public_id as replacement_worker_public_id','replacements.display_name as replacement_worker_name'])
            ->orderByDesc('requests.starts_at')->paginate(max(1,min($perPage,100)));
    }

    public function clockIn(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($data, $companyId, $actorId): array {
            $worker = $this->worker($companyId, (string) $data['worker_public_id']);
            if (! in_array((string) $worker->employment_status, ['active', 'onboarding'], true)) {
                throw new InvalidArgumentException('Worker is not operationally active.');
            }

            $active = $this->db->table('tz_attendance_sessions')
                ->where('company_id', $companyId)
                ->where('worker_id', $worker->id)
                ->whereIn('status', ['clocked_in', 'on_break'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id']);
            if ($active) {
                throw new InvalidArgumentException('Worker already has an active attendance session.');
            }

            $this->clockPolicy->assertTransition(null, 'clocked_in');
            $rosterShiftId = $this->optional('tz_roster_shifts', $companyId, $data['roster_shift_public_id'] ?? null, 'Roster shift');
            $workOrderId = $this->optional('tz_work_orders', $companyId, $data['work_order_public_id'] ?? null, 'Work order');
            $premisesId = $this->optional('tz_premises', $companyId, $data['premises_public_id'] ?? null, 'Premises');
            $clockedIn = (string) ($data['clocked_in_at'] ?? now()->format('Y-m-d H:i:s'));
            $scheduled = 0;

            if ($rosterShiftId) {
                $shift = $this->db->table('tz_roster_shifts')
                    ->where('id', $rosterShiftId)
                    ->where('company_id', $companyId)
                    ->first(['starts_at', 'ends_at', 'premises_id', 'work_order_id']);
                if ($shift) {
                    $scheduled = max(0, (int) floor((strtotime((string) $shift->ends_at) - strtotime((string) $shift->starts_at)) / 60));
                    $premisesId = $premisesId ?? ($shift->premises_id ? (int) $shift->premises_id : null);
                    $workOrderId = $workOrderId ?? ($shift->work_order_id ? (int) $shift->work_order_id : null);
                }
            }

            $verificationAttempt = $this->resolveVerificationAttempt(
                $companyId,
                (int) $worker->id,
                $premisesId,
                isset($data['verification_attempt_public_id']) ? (string) $data['verification_attempt_public_id'] : null,
            );

            $publicId = (string) Str::ulid();
            $sessionId = $this->db->table('tz_attendance_sessions')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'worker_id' => $worker->id,
                'roster_shift_id' => $rosterShiftId,
                'work_order_id' => $workOrderId,
                'premises_id' => $premisesId,
                'status' => 'clocked_in',
                'clocked_in_at' => $clockedIn,
                'timezone' => (string) ($data['timezone'] ?? config('workcore.context.default_timezone', 'Australia/Melbourne')),
                'source' => (string) ($data['source'] ?? ($verificationAttempt ? 'verified' : 'manual')),
                'clock_in_latitude' => $data['latitude'] ?? null,
                'clock_in_longitude' => $data['longitude'] ?? null,
                'scheduled_minutes' => $scheduled,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($verificationAttempt) {
                $updated = $this->db->table('tz_attendance_verification_attempts')
                    ->where('id', $verificationAttempt->id)
                    ->where('company_id', $companyId)
                    ->whereNull('attendance_session_id')
                    ->where('status', 'accepted')
                    ->update([
                        'attendance_session_id' => $sessionId,
                        'status' => 'consumed',
                        'consumed_at' => now(),
                        'updated_at' => now(),
                    ]);
                if ($updated !== 1) {
                    throw new InvalidArgumentException('Attendance verification attempt was already consumed.');
                }
            }

            return $this->attendance($companyId, $publicId);
        }, 3);
    }

    public function startBreak(string $sessionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        return $this->db->transaction(function() use($sessionPublicId,$data,$companyId,$actorId): array {
            $session=$this->sessionForUpdate($companyId,$sessionPublicId);
            $this->clockPolicy->assertTransition((string)$session->status,'on_break');
            if ($this->db->table('tz_attendance_breaks')->where('attendance_session_id',$session->id)->whereNull('ended_at')->exists()) throw new InvalidArgumentException('Attendance session already has an open break.');
            $this->db->table('tz_attendance_breaks')->insert([
                'public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'attendance_session_id'=>$session->id,'break_type'=>(string)($data['break_type']??'meal'),
                'started_at'=>(string)($data['started_at']??now()->format('Y-m-d H:i:s')),'paid'=>(bool)($data['paid']??false),'reason'=>$data['reason']??null,'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->db->table('tz_attendance_sessions')->where('id',$session->id)->update(['status'=>'on_break','updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            return $this->attendance($companyId,$sessionPublicId);
        },3);
    }

    public function endBreak(string $sessionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        return $this->db->transaction(function() use($sessionPublicId,$data,$companyId,$actorId): array {
            $session=$this->sessionForUpdate($companyId,$sessionPublicId);
            $this->clockPolicy->assertTransition((string)$session->status,'clocked_in');
            $break=$this->db->table('tz_attendance_breaks')->where('attendance_session_id',$session->id)->whereNull('ended_at')->lockForUpdate()->first();
            if(!$break) throw new InvalidArgumentException('Attendance session has no open break.');
            $ended=(string)($data['ended_at']??now()->format('Y-m-d H:i:s'));
            $minutes=$this->clockPolicy->workedMinutes((string)$break->started_at,$ended,0);
            $this->db->table('tz_attendance_breaks')->where('id',$break->id)->update(['ended_at'=>$ended,'duration_minutes'=>$minutes,'updated_at'=>now()]);
            $total=(int)$this->db->table('tz_attendance_breaks')->where('attendance_session_id',$session->id)->sum('duration_minutes');
            $this->db->table('tz_attendance_sessions')->where('id',$session->id)->update(['status'=>'clocked_in','break_minutes'=>$total,'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            return $this->attendance($companyId,$sessionPublicId);
        },3);
    }

    public function clockOut(string $sessionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        return $this->db->transaction(function() use($sessionPublicId,$data,$companyId,$actorId): array {
            $session=$this->sessionForUpdate($companyId,$sessionPublicId);
            $this->clockPolicy->assertTransition((string)$session->status,'clocked_out');
            $ended=(string)($data['clocked_out_at']??now()->format('Y-m-d H:i:s'));
            $open=$this->db->table('tz_attendance_breaks')->where('attendance_session_id',$session->id)->whereNull('ended_at')->lockForUpdate()->first();
            if($open){
                $duration=$this->clockPolicy->workedMinutes((string)$open->started_at,$ended,0);
                $this->db->table('tz_attendance_breaks')->where('id',$open->id)->update(['ended_at'=>$ended,'duration_minutes'=>$duration,'updated_at'=>now()]);
            }
            $breakMinutes=(int)$this->db->table('tz_attendance_breaks')->where('attendance_session_id',$session->id)->sum('duration_minutes');
            $worked=$this->clockPolicy->workedMinutes((string)$session->clocked_in_at,$ended,$breakMinutes);
            $this->db->table('tz_attendance_sessions')->where('id',$session->id)->update([
                'status'=>'clocked_out','clocked_out_at'=>$ended,'worked_minutes'=>$worked,'break_minutes'=>$breakMinutes,
                'clock_out_latitude'=>$data['latitude']??null,'clock_out_longitude'=>$data['longitude']??null,'notes'=>$data['notes']??$session->notes,
                'updated_by_user_id'=>$actorId,'updated_at'=>now(),
            ]);
            return $this->attendance($companyId,$sessionPublicId);
        },3);
    }

    public function submitTimesheet(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        $worker=$this->worker($companyId,(string)$data['worker_public_id']);
        $start=(string)$data['period_starts_on']; $end=(string)$data['period_ends_on'];
        if($end<$start) throw new InvalidArgumentException('Timesheet period end cannot precede its start.');
        return $this->db->transaction(function() use($companyId,$actorId,$worker,$start,$end,$data): array {
            $timesheet=$this->db->table('tz_timesheets')->where('company_id',$companyId)->where('worker_id',$worker->id)->where('period_starts_on',$start)->where('period_ends_on',$end)->whereNull('deleted_at')->lockForUpdate()->first();
            if($timesheet && !in_array((string)$timesheet->status,['draft','rejected'],true)) throw new InvalidArgumentException('Only draft or rejected timesheets may be submitted.');
            $sessions=$this->db->table('tz_attendance_sessions')->where('company_id',$companyId)->where('worker_id',$worker->id)->where('status','clocked_out')->whereDate('clocked_in_at','>=',$start)->whereDate('clocked_in_at','<=',$end)->whereNull('deleted_at')->orderBy('clocked_in_at')->get();
            if($sessions->isEmpty()) throw new InvalidArgumentException('No completed attendance sessions exist in this timesheet period.');
            $publicId=$timesheet?(string)$timesheet->public_id:(string)Str::ulid();
            if(!$timesheet){
                $id=$this->db->table('tz_timesheets')->insertGetId(['public_id'=>$publicId,'company_id'=>$companyId,'worker_id'=>$worker->id,'period_starts_on'=>$start,'period_ends_on'=>$end,'status'=>'draft','created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
            } else $id=(int)$timesheet->id;
            $this->db->table('tz_timesheet_entries')->where('timesheet_id',$id)->delete();
            $worked=0;$breaks=0;$overtime=0;
            foreach($sessions as $session){
                $entryWorked=(int)$session->worked_minutes; $entryBreak=(int)$session->break_minutes; $entryOver=max(0,$entryWorked-480);
                $worked+=$entryWorked;$breaks+=$entryBreak;$overtime+=$entryOver;
                $this->db->table('tz_timesheet_entries')->insert([
                    'public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'timesheet_id'=>$id,'attendance_session_id'=>$session->id,'work_order_id'=>$session->work_order_id,'premises_id'=>$session->premises_id,
                    'work_date'=>substr((string)$session->clocked_in_at,0,10),'started_at'=>$session->clocked_in_at,'ended_at'=>$session->clocked_out_at,'worked_minutes'=>$entryWorked,'break_minutes'=>$entryBreak,'overtime_minutes'=>$entryOver,'source'=>'attendance','status'=>'submitted','notes'=>$session->notes,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            $this->db->table('tz_timesheets')->where('id',$id)->update(['status'=>'submitted','worked_minutes'=>$worked,'break_minutes'=>$breaks,'overtime_minutes'=>$overtime,'submitted_at'=>now(),'approved_at'=>null,'approved_by_user_id'=>null,'decision_reason'=>null,'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            return $this->timesheet($companyId,$publicId);
        },3);
    }

    public function decideTimesheet(string $timesheetPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        return $this->db->transaction(function() use($timesheetPublicId,$data,$companyId,$actorId): array {
            $sheet=$this->db->table('tz_timesheets')->where('company_id',$companyId)->where('public_id',$timesheetPublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if(!$sheet) throw new InvalidArgumentException('Timesheet does not belong to the active company.');
            $status=(string)$data['status']; $reason=$data['reason']??null;
            $this->timesheetPolicy->assertTransition((string)$sheet->status,$status,$reason);
            $update=['status'=>$status,'decision_reason'=>$reason,'updated_by_user_id'=>$actorId,'updated_at'=>now()];
            if($status==='approved'){$update['approved_at']=now();$update['approved_by_user_id']=$actorId;}
            $this->db->table('tz_timesheets')->where('id',$sheet->id)->update($update);
            $this->db->table('tz_timesheet_entries')->where('timesheet_id',$sheet->id)->update(['status'=>$status,'updated_at'=>now()]);
            return $this->timesheet($companyId,$timesheetPublicId);
        },3);
    }

    public function createLeaveType(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        $code=Str::upper(trim((string)$data['code']));
        if($this->db->table('tz_leave_types')->where('company_id',$companyId)->where('code',$code)->whereNull('deleted_at')->exists()) throw new InvalidArgumentException('Leave type code already exists.');
        $publicId=(string)Str::ulid();
        $this->db->table('tz_leave_types')->insert(['public_id'=>$publicId,'company_id'=>$companyId,'code'=>$code,'name'=>(string)$data['name'],'paid'=>(bool)($data['paid']??false),'unit'=>(string)($data['unit']??'minutes'),'requires_document'=>(bool)($data['requires_document']??false),'max_consecutive_days'=>$data['max_consecutive_days']??null,'active'=>(bool)($data['active']??true),'settings'=>isset($data['settings'])?json_encode($data['settings'],JSON_THROW_ON_ERROR):null,'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
        return (array)$this->db->table('tz_leave_types')->where('company_id',$companyId)->where('public_id',$publicId)->first();
    }

    public function createLeaveRequest(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        $worker=$this->worker($companyId,(string)$data['worker_public_id']);
        $type=$this->db->table('tz_leave_types')->where('company_id',$companyId)->where('public_id',(string)$data['leave_type_public_id'])->where('active',true)->whereNull('deleted_at')->first();
        if(!$type) throw new InvalidArgumentException('Leave type does not belong to the active company or is inactive.');
        if((bool)$type->requires_document && empty($data['document_path'])) throw new InvalidArgumentException('This leave type requires a supporting document.');
        $minutes=$this->leavePolicy->requestedMinutes((string)$data['starts_at'],(string)$data['ends_at']);
        $overlap=$this->db->table('tz_leave_requests')->where('company_id',$companyId)->where('worker_id',$worker->id)->whereIn('status',['submitted','approved'])->where('starts_at','<',(string)$data['ends_at'])->where('ends_at','>',(string)$data['starts_at'])->whereNull('deleted_at')->exists();
        if($overlap) throw new InvalidArgumentException('Worker already has overlapping submitted or approved leave.');
        $replacementId=$this->optional('tz_workers',$companyId,$data['replacement_worker_public_id']??null,'Replacement worker');
        $status=(string)($data['status']??'submitted');
        if(!in_array($status,['draft','submitted'],true)) throw new InvalidArgumentException('New leave requests must be draft or submitted.');
        if($status==='submitted') $this->leavePolicy->assertTransition('draft','submitted',null);
        $publicId=(string)Str::ulid();
        $id=$this->db->table('tz_leave_requests')->insertGetId([
            'public_id'=>$publicId,'company_id'=>$companyId,'worker_id'=>$worker->id,'leave_type_id'=>$type->id,'replacement_worker_id'=>$replacementId,
            'starts_at'=>(string)$data['starts_at'],'ends_at'=>(string)$data['ends_at'],'timezone'=>(string)($data['timezone']??config('workcore.context.default_timezone','Australia/Melbourne')),
            'requested_minutes'=>$minutes,'status'=>$status,'reason'=>$data['reason']??null,'notes'=>$data['notes']??null,'document_path'=>$data['document_path']??null,
            'affects_roster'=>(bool)($data['affects_roster']??true),'submitted_at'=>$status==='submitted'?now():null,'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->appendLeaveHistory($companyId,$id,null,$status,$data['reason']??null,$actorId);
        return $this->leaveRequest($companyId,$publicId);
    }

    public function changeLeaveRequestStatus(string $requestPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);
        return $this->db->transaction(function() use($requestPublicId,$data,$companyId,$actorId): array {
            $request=$this->db->table('tz_leave_requests')->where('company_id',$companyId)->where('public_id',$requestPublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if(!$request) throw new InvalidArgumentException('Leave request does not belong to the active company.');
            $status=(string)$data['status'];$reason=$data['reason']??null;
            $this->leavePolicy->assertTransition((string)$request->status,$status,$reason);
            if($status==='approved'){
                $balance=$this->db->table('tz_leave_balances')->where('company_id',$companyId)->where('worker_id',$request->worker_id)->where('leave_type_id',$request->leave_type_id)->where('balance_year',(int)substr((string)$request->starts_at,0,4))->lockForUpdate()->first();
                if($balance && (int)$balance->available_minutes < (int)$request->requested_minutes) throw new InvalidArgumentException('Worker does not have sufficient leave balance.');
                if($balance) $this->db->table('tz_leave_balances')->where('id',$balance->id)->update(['taken_minutes'=>(int)$balance->taken_minutes+(int)$request->requested_minutes,'available_minutes'=>(int)$balance->available_minutes-(int)$request->requested_minutes,'updated_at'=>now()]);
            }
            if($status==='cancelled' && (string)$request->status==='approved'){
                $balance=$this->db->table('tz_leave_balances')->where('company_id',$companyId)->where('worker_id',$request->worker_id)->where('leave_type_id',$request->leave_type_id)->where('balance_year',(int)substr((string)$request->starts_at,0,4))->lockForUpdate()->first();
                if($balance) $this->db->table('tz_leave_balances')->where('id',$balance->id)->update(['taken_minutes'=>max(0,(int)$balance->taken_minutes-(int)$request->requested_minutes),'available_minutes'=>(int)$balance->available_minutes+(int)$request->requested_minutes,'updated_at'=>now()]);
            }
            $update=['status'=>$status,'decision_reason'=>$reason,'updated_by_user_id'=>$actorId,'updated_at'=>now()];
            if(in_array($status,['approved','declined'],true)){$update['decided_at']=now();$update['decided_by_user_id']=$actorId;}
            if($status==='submitted')$update['submitted_at']=now();
            $this->db->table('tz_leave_requests')->where('id',$request->id)->update($update);
            $this->appendLeaveHistory($companyId,(int)$request->id,(string)$request->status,$status,$reason,$actorId);
            $record=$this->leaveRequest($companyId,$requestPublicId);
            $record['impacted_roster_shifts']=$this->db->table('tz_roster_shift_assignments as assignments')->join('tz_roster_shifts as shifts','shifts.id','=','assignments.roster_shift_id')->where('assignments.company_id',$companyId)->where('assignments.worker_id',$request->worker_id)->whereIn('assignments.status',['assigned','accepted'])->where('shifts.starts_at','<',$request->ends_at)->where('shifts.ends_at','>',$request->starts_at)->count();
            return $record;
        },3);
    }

    private function resolveVerificationAttempt(
        int $companyId,
        int $workerId,
        ?int $premisesId,
        ?string $attemptPublicId,
    ): ?object {
        $policy = null;
        if ($premisesId !== null) {
            $policy = $this->db->table('tz_attendance_policies')
                ->where('company_id', $companyId)
                ->where('premises_id', $premisesId)
                ->where('active', true)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();
        }
        if (! $policy) {
            $policy = $this->db->table('tz_attendance_policies')
                ->where('company_id', $companyId)
                ->whereNull('premises_id')
                ->where('active', true)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();
        }

        if ($attemptPublicId === null || $attemptPublicId === '') {
            if ($policy && (bool) $policy->requires_verification) {
                throw new InvalidArgumentException('This attendance policy requires a verified clock-in attempt.');
            }

            return null;
        }

        $attempt = $this->db->table('tz_attendance_verification_attempts')
            ->where('company_id', $companyId)
            ->where('worker_id', $workerId)
            ->where('public_id', $attemptPublicId)
            ->where('status', 'accepted')
            ->whereNull('attendance_session_id')
            ->where('expires_at', '>=', now())
            ->lockForUpdate()
            ->first();

        if (! $attempt) {
            throw new InvalidArgumentException('Attendance verification attempt is invalid, expired, rejected, or already consumed.');
        }
        if ($policy && $attempt->policy_id !== null && (int) $attempt->policy_id !== (int) $policy->id) {
            throw new InvalidArgumentException('Attendance verification attempt does not satisfy the active attendance policy.');
        }
        if ($premisesId !== null && $attempt->premises_id !== null && (int) $attempt->premises_id !== $premisesId) {
            throw new InvalidArgumentException('Attendance verification attempt belongs to a different premises.');
        }

        return $attempt;
    }

    private function attendance(int $companyId,string $publicId): array
    {
        $row=$this->db->table('tz_attendance_sessions as sessions')->join('tz_workers as workers','workers.id','=','sessions.worker_id')->leftJoin('tz_premises as premises','premises.id','=','sessions.premises_id')->where('sessions.company_id',$companyId)->where('sessions.public_id',$publicId)->first(['sessions.*','workers.public_id as worker_public_id','workers.display_name as worker_name','premises.public_id as premises_public_id','premises.name as premises_name']);
        return $row?(array)$row:['public_id'=>$publicId];
    }
    private function timesheet(int $companyId,string $publicId): array
    {
        $row=$this->db->table('tz_timesheets as sheets')->join('tz_workers as workers','workers.id','=','sheets.worker_id')->where('sheets.company_id',$companyId)->where('sheets.public_id',$publicId)->first(['sheets.*','workers.public_id as worker_public_id','workers.display_name as worker_name']);
        $record=$row?(array)$row:['public_id'=>$publicId];
        if($row)$record['entries']=$this->db->table('tz_timesheet_entries')->where('timesheet_id',$row->id)->orderBy('work_date')->get()->map(static fn($x)=>(array)$x)->all();
        return $record;
    }
    private function leaveRequest(int $companyId,string $publicId): array
    {
        $row=$this->db->table('tz_leave_requests as requests')->join('tz_workers as workers','workers.id','=','requests.worker_id')->join('tz_leave_types as types','types.id','=','requests.leave_type_id')->leftJoin('tz_workers as replacements','replacements.id','=','requests.replacement_worker_id')->where('requests.company_id',$companyId)->where('requests.public_id',$publicId)->first(['requests.*','workers.public_id as worker_public_id','workers.display_name as worker_name','types.public_id as leave_type_public_id','types.name as leave_type_name','types.paid as leave_is_paid','replacements.public_id as replacement_worker_public_id','replacements.display_name as replacement_worker_name']);
        return $row?(array)$row:['public_id'=>$publicId];
    }
    private function sessionForUpdate(int $companyId,string $publicId): object
    {
        $row=$this->db->table('tz_attendance_sessions')->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->lockForUpdate()->first();
        if(!$row) throw new InvalidArgumentException('Attendance session does not belong to the active company.');
        return $row;
    }
    private function worker(int $companyId,string $publicId): object
    {
        $row=$this->db->table('tz_workers')->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->first();
        if(!$row) throw new InvalidArgumentException('Worker does not belong to the active company.');
        return $row;
    }
    private function optional(string $table,int $companyId,mixed $publicId,string $label): ?int
    {
        if($publicId===null||$publicId==='')return null;
        $id=$this->db->table($table)->where('company_id',$companyId)->where('public_id',(string)$publicId)->value('id');
        if(!$id)throw new InvalidArgumentException("{$label} does not belong to the active company.");
        return (int)$id;
    }
    private function appendLeaveHistory(int $companyId,int $requestId,?string $from,string $to,?string $reason,int $actorId): void
    {
        $this->db->table('tz_leave_request_history')->insert(['company_id'=>$companyId,'leave_request_id'=>$requestId,'from_status'=>$from,'to_status'=>$to,'reason'=>$reason,'actor_user_id'=>$actorId,'created_at'=>now()]);
    }
    private function assertActor(int $companyId,int $actorId): void
    {
        $this->assertCompany($companyId);
        if($actorId<=0)throw new InvalidArgumentException('Attendance actions require an authenticated MagicAI user.');
    }
}
