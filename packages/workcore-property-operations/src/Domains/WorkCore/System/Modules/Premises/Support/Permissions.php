<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Support;

class Permissions
{
    public function all(): array
    {
        $groups = config('managedpremises.manifests.permissions', []);
        if (!$groups) {
            $path = dirname(__DIR__) . '/manifests/permissions.php';
            $groups = is_file($path) ? require $path : [];
        }

        $permissions = [];
        array_walk_recursive($groups, static function ($value) use (&$permissions): void {
            if (is_string($value)) {
                $permissions[] = $value;
            }
        });

        return array_values(array_unique($permissions));
    }
}
