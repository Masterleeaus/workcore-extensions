<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Concerns;

trait EnsuresManagedPremisesPermissions
{
    protected function ensureCanViewManagedPremises(): void
    {
        // Worksuite convention: permission() returns 'all', 'added', 'owned', 'none', etc.
        if (function_exists('user') && user() && user()->permission('view_managedpremises') == 'none') {
            abort(403);
        }
    }
}
