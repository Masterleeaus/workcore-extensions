<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Policies;

use App\Domains\WorkCore\Contracts\WorkCoreActor;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyMeterReading;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PropertyMeterReadingPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly PermissionResolverContract $permissions,
        private readonly TenantContextContract $tenant,
    ) {}

    public function viewAny(mixed $actor): bool
    {
        return $this->allowed($actor, 'business.premises.meter_readings.view');
    }

    public function view(mixed $actor, PropertyMeterReading $reading): bool
    {
        return $this->sameCompany($reading) && $this->viewAny($actor);
    }

    public function create(mixed $actor): bool
    {
        return $this->allowed($actor, 'business.premises.meter_readings.record');
    }

    public function delete(mixed $actor, PropertyMeterReading $reading): bool
    {
        return $this->sameCompany($reading)
            && $this->allowed($actor, 'business.premises.meter_readings.manage');
    }

    private function sameCompany(PropertyMeterReading $reading): bool
    {
        return $this->tenant->hasTenant()
            && (int) $reading->getAttribute('company_id') === $this->tenant->companyId();
    }

    private function allowed(mixed $actor, string $permission): bool
    {
        if (! $this->tenant->hasTenant()) {
            return false;
        }

        $actorId = $this->actorId($actor);
        return $actorId !== null
            && $this->permissions->allows($actorId, $this->tenant->companyId(), $permission);
    }

    private function actorId(mixed $actor): int|string|null
    {
        if ($actor instanceof WorkCoreActor) {
            return $actor->getActorId();
        }

        if (is_object($actor) && method_exists($actor, 'getAuthIdentifier')) {
            $id = $actor->getAuthIdentifier();
            return is_int($id) || is_string($id) ? $id : null;
        }

        return null;
    }
}
