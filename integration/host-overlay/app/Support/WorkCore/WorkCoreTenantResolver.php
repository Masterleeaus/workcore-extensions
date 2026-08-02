<?php

declare(strict_types=1);

namespace App\Support\WorkCore;

use App\Domains\WorkCore\System\Contracts\TenantResolverContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkCoreTenantResolver implements TenantResolverContract
{
    public function resolve(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $candidate = $request->header((string) config('workcore.tenancy.header', 'X-Titan-Company'))
            ?? $request->session()->get((string) config('workcore.tenancy.session_key', 'titan_company_id'))
            ?? $user->active_company_id;

        if (! is_numeric($candidate)) {
            return null;
        }

        $companyId = (int) $candidate;
        $member = DB::table('tz_company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'active')
            ->exists();

        if (! $member) {
            return null;
        }

        if ((int) $user->active_company_id !== $companyId) {
            $user->forceFill(['active_company_id' => $companyId])->saveQuietly();
        }

        $request->session()->put((string) config('workcore.tenancy.session_key', 'titan_company_id'), $companyId);

        return ['company_id' => $companyId, 'user_id' => (int) $user->getAuthIdentifier()];
    }
}
