<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class ClockInAttendance implements BusinessActionHandlerContract
{
    public function __construct(private AttendanceRepositoryContract $repository){}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->clockIn($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record,new TypedReference('attendance_session',(string)$record['public_id']),[new PendingDomainEvent('workcore.attendance.clocked_in',1,['attendance'=>$record])]);
    }
}
