<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        if (!function_exists('company_id') || !company_id()) {
            abort(403, 'Company context required.');
        }
        return $next($request);
    }
}
