<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyScope
{
    public function handle(Request $request, Closure $next)
    {
        if (!function_exists('company') || !company()) {
            abort(403);
        }

        return $next($request);
    }
}
