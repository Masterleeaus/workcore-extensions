<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->active_company_id === null, 409, 'No active company has been selected.');
        return $next($request);
    }
}
