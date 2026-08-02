<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Policies;

use App\Domains\WorkCore\System\Modules\Premises\Policies\Concerns\ChecksPmPermissions;

class PropertyPolicy
{
    use ChecksPmPermissions;

    public function viewAny($user): bool { return $this->has($user, 'managedpremises.view'); }
    public function view($user): bool { return $this->has($user, 'managedpremises.view'); }
    public function create($user): bool { return $this->has($user, 'managedpremises.create'); }
    public function update($user): bool { return $this->has($user, 'managedpremises.update'); }
    public function delete($user): bool { return $this->has($user, 'managedpremises.delete'); }
}
