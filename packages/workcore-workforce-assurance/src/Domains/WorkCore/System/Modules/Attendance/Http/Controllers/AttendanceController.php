<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\{AttendanceResource,LeaveRequestResource};
use App\Domains\WorkCore\System\Modules\Attendance\Actions\{SearchAttendance,SearchLeaveRequests};
use App\Domains\WorkCore\System\Modules\Attendance\Http\Requests\{ChangeLeaveRequestStatusRequest,ClockInRequest,ClockOutRequest,DecideTimesheetRequest,EndBreakRequest,StartBreakRequest,StoreLeaveRequest,StoreLeaveTypeRequest,SubmitTimesheetRequest};
use Illuminate\Http\{JsonResponse,Request};
final class AttendanceController
{
    public function index(Request $request,SearchAttendance $search,ApiResponseFactory $api): JsonResponse { return $api->paginated($search->execute($request->only(['status','worker_public_id','premises_public_id','from','to']),$request->integer('per_page',25)),AttendanceResource::make(...)); }
    public function leaveRequests(Request $request,SearchLeaveRequests $search,ApiResponseFactory $api): JsonResponse { return $api->paginated($search->execute($request->only(['status','worker_public_id','leave_type_public_id','from','to']),$request->integer('per_page',25)),LeaveRequestResource::make(...)); }
    public function clockIn(ClockInRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.attendance.clock_in',$request->validated(),$request,$d,$api);}
    public function startBreak(string $attendance,StartBreakRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.attendance.break.start',['attendance_session_public_id'=>$attendance]+$request->validated(),$request,$d,$api);}
    public function endBreak(string $attendance,EndBreakRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.attendance.break.end',['attendance_session_public_id'=>$attendance]+$request->validated(),$request,$d,$api);}
    public function clockOut(string $attendance,ClockOutRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.attendance.clock_out',['attendance_session_public_id'=>$attendance]+$request->validated(),$request,$d,$api);}
    public function submitTimesheet(SubmitTimesheetRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.timesheet.submit',$request->validated(),$request,$d,$api);}
    public function decideTimesheet(string $timesheet,DecideTimesheetRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.timesheet.decide',['timesheet_public_id'=>$timesheet]+$request->validated(),$request,$d,$api);}
    public function storeLeaveType(StoreLeaveTypeRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.leave_type.create',$request->validated(),$request,$d,$api);}
    public function storeLeaveRequest(StoreLeaveRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.leave_request.create',$request->validated(),$request,$d,$api);}
    public function leaveStatus(string $leaveRequest,ChangeLeaveRequestStatusRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.leave_request.change_status',['leave_request_public_id'=>$leaveRequest]+$request->validated(),$request,$d,$api);}
    private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api): JsonResponse
    {
        $tenant=workcore_tenant();$idempotency=trim((string)$request->header('Idempotency-Key'));abort_if($idempotency==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($key,$payload,(int)$tenant->companyId(),(int)$tenant->userId(),$idempotency,$request->header('X-WorkCore-Confirmation'),'api'));
        return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));
    }
}
