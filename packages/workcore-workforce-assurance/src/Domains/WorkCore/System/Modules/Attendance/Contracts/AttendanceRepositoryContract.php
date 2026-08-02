<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Attendance\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AttendanceRepositoryContract
{
    public function searchAttendance(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchLeaveRequests(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function clockIn(array $data, int $companyId, int $actorId): array;
    public function startBreak(string $sessionPublicId, array $data, int $companyId, int $actorId): array;
    public function endBreak(string $sessionPublicId, array $data, int $companyId, int $actorId): array;
    public function clockOut(string $sessionPublicId, array $data, int $companyId, int $actorId): array;
    public function submitTimesheet(array $data, int $companyId, int $actorId): array;
    public function decideTimesheet(string $timesheetPublicId, array $data, int $companyId, int $actorId): array;
    public function createLeaveType(array $data, int $companyId, int $actorId): array;
    public function createLeaveRequest(array $data, int $companyId, int $actorId): array;
    public function changeLeaveRequestStatus(string $requestPublicId, array $data, int $companyId, int $actorId): array;
}
