<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Attendance\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.attendance.route_prefix'))->middleware((array)config('workcore.attendance.middleware'))->name('api.workcore.v1.')->group(function(): void {
    Route::get('attendance',[AttendanceController::class,'index'])->name('attendance.index');
    Route::post('attendance/clock-in',[AttendanceController::class,'clockIn'])->name('attendance.clock-in');
    Route::post('attendance/{attendance}/breaks/start',[AttendanceController::class,'startBreak'])->name('attendance.breaks.start');
    Route::post('attendance/{attendance}/breaks/end',[AttendanceController::class,'endBreak'])->name('attendance.breaks.end');
    Route::post('attendance/{attendance}/clock-out',[AttendanceController::class,'clockOut'])->name('attendance.clock-out');
    Route::post('timesheets/submit',[AttendanceController::class,'submitTimesheet'])->name('timesheets.submit');
    Route::post('timesheets/{timesheet}/decision',[AttendanceController::class,'decideTimesheet'])->name('timesheets.decision');
    Route::post('leave-types',[AttendanceController::class,'storeLeaveType'])->name('leave-types.store');
    Route::get('leave-requests',[AttendanceController::class,'leaveRequests'])->name('leave-requests.index');
    Route::post('leave-requests',[AttendanceController::class,'storeLeaveRequest'])->name('leave-requests.store');
    Route::post('leave-requests/{leaveRequest}/status',[AttendanceController::class,'leaveStatus'])->name('leave-requests.status');
});
