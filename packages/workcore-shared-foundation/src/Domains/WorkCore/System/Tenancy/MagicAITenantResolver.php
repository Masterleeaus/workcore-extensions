<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Contracts\TenantResolverContract;
use App\Domains\WorkCore\System\Identity\MagicAIUserCompanyAdapter;
use Illuminate\Http\Request;

final class MagicAITenantResolver implements TenantResolverContract
{
    public function __construct(private MagicAIUserCompanyAdapter $adapter) {}

    public function resolve(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $userId = (int) $user->getAuthIdentifier();
        $header = (string) config('workcore.tenancy.header', 'X-Titan-Company');
        $sessionCompanyId = $request->hasSession()
            ? $request->session()->get((string) config('workcore.tenancy.session_key', 'titan_company_id'))
            : null;
        $requestedCompanyId = $request->header($header)
            ?? $sessionCompanyId
            ?? $this->adapter->activeCompanyId($user);

        if (! is_numeric($requestedCompanyId) || (int) $requestedCompanyId < 1) {
            return null;
        }

        $companyId = (int) $requestedCompanyId;
        if (! $this->adapter->belongsToCompany($userId, $companyId)) {
            abort(403, 'You do not belong to the selected Titan company.');
        }

        return ['company_id' => $companyId, 'user_id' => $userId];
    }
}
