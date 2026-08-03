<?php

declare(strict_types=1);

namespace App\Support\WorkCore;

use App\Domains\WorkCore\System\Contracts\TenantResolverContract;
use App\Domains\WorkCore\System\Identity\WorkCoreIdentityContextResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkCoreTenantResolver implements TenantResolverContract
{
    public function __construct(private WorkCoreIdentityContextResolver $identity) {}

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
        $userId = (int) $user->getAuthIdentifier();
        $membership = DB::table('tz_company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first(['id', 'role_id', 'role_key', 'is_owner', 'updated_at']);

        if ($membership === null) {
            return null;
        }

        if ((int) $user->getAttribute($activeCompanyColumn) !== $companyId) {
            $user->forceFill([$activeCompanyColumn => $companyId])->saveQuietly();
        }

        if ($request->hasSession()) {
            $request->session()->put($sessionKey, $companyId);
        }

        return $this->identity->resolve($request, $companyId, $userId, $membership);
    }
}
