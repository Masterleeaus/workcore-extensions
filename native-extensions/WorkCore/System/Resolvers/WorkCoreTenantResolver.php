<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Resolvers;

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

        $sessionKey = (string) config('workcore.tenancy.session_key', 'titan_company_id');
        $sessionCompany = $request->hasSession() ? $request->session()->get($sessionKey) : null;
        $activeCompanyColumn = (string) config('workcore.identity.active_company_column', 'active_company_id');
        $candidate = $request->header((string) config('workcore.tenancy.header', 'X-Titan-Company'))
            ?? $sessionCompany
            ?? $user->getAttribute($activeCompanyColumn);

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

        if ((int) $user->getAttribute($activeCompanyColumn) !== $companyId) {
            $user->forceFill([$activeCompanyColumn => $companyId])->saveQuietly();
        }

        if ($request->hasSession()) {
            $request->session()->put($sessionKey, $companyId);
        }

        return ['company_id' => $companyId, 'user_id' => (int) $user->getAuthIdentifier()];
    }
}
