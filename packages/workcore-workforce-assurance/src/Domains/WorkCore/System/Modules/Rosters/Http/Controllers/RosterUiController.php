<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Controllers;

use Illuminate\Contracts\View\View;

final class RosterUiController
{
    public function index(): View
    {
        return view('workcore::rosters.index', [
            'apiPrefix' => trim((string) config('workcore.rosters.route_prefix', 'api/workcore/v1'), '/'),
            'timezone' => (string) config('workcore.context.default_timezone', 'Australia/Melbourne'),
        ]);
    }
}
