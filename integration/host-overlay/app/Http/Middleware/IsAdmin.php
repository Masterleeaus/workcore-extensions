<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $companyId = $user->active_company_id;
        $allowed = $companyId && DB::table('tz_company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('is_owner', true)->orWhereIn('role_key', ['owner', 'admin']);
            })
            ->exists();

        abort_unless($allowed, 403);
        return $next($request);
    }
}
