<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Policies;

use App\Domains\WorkCore\System\Modules\Premises\Policies\Concerns\ChecksPmPermissions;

class PropertyServicePlanPolicy
{
    use ChecksPmPermissions;

    public function viewAny($user): bool { return $this->has($user, 'managedpremises.plans.view'); }
    public function view($user): bool { return $this->has($user, 'managedpremises.plans.view'); }
    public function create($user): bool { return $this->has($user, 'managedpremises.plans.create'); }
    public function update($user): bool { return $this->has($user, 'managedpremises.plans.update'); }
    public function delete($user): bool { return $this->has($user, 'managedpremises.plans.delete'); }
}
