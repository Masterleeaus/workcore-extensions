<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Actions;

use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\Scheduling\Contracts\AppointmentRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SearchAppointments
{
    public function __construct(
        private AppointmentRepositoryContract $repository,
        private PermissionResolverContract $permissions,
    ) {}

    public function execute(
        ?string $query,
        ?string $status,
        ?string $appointmentType,
        ?string $customerPublicId,
        ?string $premisesPublicId,
        ?string $workOrderPublicId,
        ?string $startsFrom,
        ?string $startsTo,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $tenant = workcore_tenant();
        $actorId = (int) $tenant->userId();
        $companyId = (int) $tenant->companyId();
        $accessLevel = $this->permissions->accessLevel(
            $actorId,
            $companyId,
            (string) config('workcore.scheduling.permissions.view'),
        );
        abort_if($accessLevel === WorkCoreAccessLevel::None, 403);

        return $this->repository->search(
            companyId: $companyId,
            accessLevel: $accessLevel,
            actorId: $actorId,
            query: $query,
            status: $status,
            appointmentType: $appointmentType,
            customerPublicId: $customerPublicId,
            premisesPublicId: $premisesPublicId,
            workOrderPublicId: $workOrderPublicId,
            startsFrom: $startsFrom,
            startsTo: $startsTo,
            perPage: $perPage,
        );
    }
}
