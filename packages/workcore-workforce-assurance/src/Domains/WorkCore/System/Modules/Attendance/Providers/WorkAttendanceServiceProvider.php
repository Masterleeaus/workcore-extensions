<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\{AttendanceAccessContract,LeaveRequestAccessContract};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\{AttendanceAccessAdapter,LeaveRequestAccessAdapter};
use App\Domains\WorkCore\System\Modules\Attendance\Actions\{ChangeLeaveRequestStatus,ClockInAttendance,ClockOutAttendance,CreateLeaveRequest,CreateLeaveType,DecideTimesheet,EndAttendanceBreak,SearchAttendance,SearchLeaveRequests,StartAttendanceBreak,SubmitTimesheet};
use App\Domains\WorkCore\System\Modules\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Attendance\Repositories\EloquentAttendanceRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkAttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendanceRepositoryContract::class,EloquentAttendanceRepository::class);
        $this->app->bind(AttendanceAccessContract::class,AttendanceAccessAdapter::class);
        $this->app->bind(LeaveRequestAccessContract::class,LeaveRequestAccessAdapter::class);
        $a=$this->app->make(BusinessActionRegistry::class);
        $a->register(new ActionDefinition('workcore.attendance.clock_in',ClockInAttendance::class,'medium',true,'workcore.attendance',(string)config('workcore.attendance.permissions.clock')));
        $a->register(new ActionDefinition('workcore.attendance.break.start',StartAttendanceBreak::class,'low',true,'workcore.attendance',(string)config('workcore.attendance.permissions.clock')));
        $a->register(new ActionDefinition('workcore.attendance.break.end',EndAttendanceBreak::class,'low',true,'workcore.attendance',(string)config('workcore.attendance.permissions.clock')));
        $a->register(new ActionDefinition('workcore.attendance.clock_out',ClockOutAttendance::class,'medium',true,'workcore.attendance',(string)config('workcore.attendance.permissions.clock')));
        $a->register(new ActionDefinition('workcore.timesheet.submit',SubmitTimesheet::class,'medium',true,'workcore.attendance',(string)config('workcore.attendance.permissions.timesheets_submit')));
        $a->register(new ActionDefinition('workcore.timesheet.decide',DecideTimesheet::class,'high',true,'workcore.attendance',(string)config('workcore.attendance.permissions.timesheets_approve')));
        $a->register(new ActionDefinition('workcore.leave_type.create',CreateLeaveType::class,'high',true,'workcore.attendance',(string)config('workcore.attendance.permissions.leave_manage')));
        $a->register(new ActionDefinition('workcore.leave_request.create',CreateLeaveRequest::class,'medium',true,'workcore.attendance',(string)config('workcore.attendance.permissions.leave_request')));
        $a->register(new ActionDefinition('workcore.leave_request.change_status',ChangeLeaveRequestStatus::class,'high',true,'workcore.attendance',(string)config('workcore.attendance.permissions.leave_approve')));
        $r=$this->app->make(ReadModelRegistry::class);
        $r->register(new ReadModelDefinition('workcore.attendance.search',SearchAttendance::class,'workcore.attendance', permission: (string) config('workcore.attendance.permissions.view')));
        $r->register(new ReadModelDefinition('workcore.leave_request.search',SearchLeaveRequests::class,'workcore.attendance', permission: (string) config('workcore.attendance.permissions.leave_view')));
    }
    public function boot(): void { if((bool)config('workcore.attendance.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php'); }
}
