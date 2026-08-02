<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\AI;
use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};
final class AttendanceToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [
            T::read('attendance_search_records','Search company attendance records using authorised filters.','workcore.attendance.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)]),'restricted'),
            T::read('attendance_search_leave_requests','Search authorised leave requests.','workcore.leave_request.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)]),'restricted'),
        ];
    }
}
