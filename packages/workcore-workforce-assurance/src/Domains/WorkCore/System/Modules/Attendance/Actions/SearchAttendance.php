<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Attendance\Actions;
use App\Domains\WorkCore\System\Modules\Attendance\Contracts\AttendanceRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchAttendance { public function __construct(private AttendanceRepositoryContract $repository){} public function execute(array $filters=[],int $perPage=25): LengthAwarePaginator { $tenant=workcore_tenant(); return $this->repository->searchAttendance((int)$tenant->companyId(),$filters,$perPage); } }
