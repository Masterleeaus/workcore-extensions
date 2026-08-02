<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class DecideTimesheet implements BusinessActionHandlerContract
{
    public function __construct(private AttendanceRepositoryContract $repository){}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->decideTimesheet((string)$request->payload['timesheet_public_id'], $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record,new TypedReference('timesheet',(string)$record['public_id']),[new PendingDomainEvent('workcore.timesheet.status_changed',1,['timesheet'=>$record])]);
    }
}
