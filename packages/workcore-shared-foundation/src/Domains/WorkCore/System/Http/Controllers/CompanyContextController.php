<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Controllers;

use App\Domains\WorkCore\System\Http\Requests\SwitchCompanyRequest;
use App\Domains\WorkCore\System\Identity\MagicAIUserCompanyAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

final class CompanyContextController extends Controller
{
    public function switch(SwitchCompanyRequest $request, MagicAIUserCompanyAdapter $adapter): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $userId = (int) $user->getAuthIdentifier();
        $companyId = (int) $request->validated('company_id');

        abort_unless($adapter->belongsToCompany($userId, $companyId), 403);

        if ($request->hasSession()) {
            $request->session()->put(
                (string) config('workcore.tenancy.session_key', 'titan_company_id'),
                $companyId,
            );
        }

        $column = (string) config('workcore.identity.active_company_column', 'active_company_id');
        if (
            $column !== ''
            && (bool) config('workcore.tenancy.persist_active_company', false)
            && method_exists($user, 'getTable')
            && Schema::hasColumn((string) $user->getTable(), $column)
        ) {
            $user->forceFill([$column => $companyId])->saveQuietly();
        }

        return response()->json(['company_id' => $companyId, 'active' => true]);
    }
}
