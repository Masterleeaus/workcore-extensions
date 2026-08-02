<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Policies;

use App\Domains\WorkCore\System\Modules\Premises\Policies\Concerns\ChecksPmPermissions;

/**
 * @deprecated Inspection permission ownership is in QualityControl.
 *             Legacy permission names (managedpremises.inspections.*) are kept as
 *             compatibility aliases. New code should use QualityControl permissions.
 *
 * Alias mapping:
 *   managedpremises.inspections.view   → add_quality_control / view_quality_control
 *   managedpremises.inspections.create → add_quality_control
 *   managedpremises.inspections.update → edit_quality_control
 *   managedpremises.inspections.delete → delete_quality_control
 */
class PropertyInspectionPolicy
{
    use ChecksPmPermissions;

    public function viewAny($user): bool
    {
        return $this->hasAny($user, ['managedpremises.inspections.view', 'add_quality_control', 'view_quality_control']);
    }

    public function view($user): bool
    {
        return $this->hasAny($user, ['managedpremises.inspections.view', 'view_quality_control']);
    }

    public function create($user): bool
    {
        return $this->hasAny($user, ['managedpremises.inspections.create', 'add_quality_control']);
    }

    public function update($user): bool
    {
        return $this->hasAny($user, ['managedpremises.inspections.update', 'edit_quality_control']);
    }

    public function delete($user): bool
    {
        return $this->hasAny($user, ['managedpremises.inspections.delete', 'delete_quality_control']);
    }

    private function hasAny($user, array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($this->has($user, $perm)) {
                return true;
            }
        }
        return false;
    }
}
