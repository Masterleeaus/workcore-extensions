<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Controllers;

use App\Domains\WorkCore\System\Company\Actions\CreateCompany;
use App\Domains\WorkCore\System\Company\DTOs\CreateCompanyData;
use App\Domains\WorkCore\System\Http\Requests\CreateCompanyRequest;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

final class CompanyLifecycleController extends Controller
{
    public function store(CreateCompanyRequest $request, CreateCompany $action): JsonResponse
    {
        $data = $request->validated();
        $company = $action->execute(new CreateCompanyData(
            ownerUserId: (int) $request->user()->getAuthIdentifier(), name: $data['name'], slug: $data['slug'],
            timezone: $data['timezone'] ?? 'Australia/Melbourne', currencyCode: $data['currency_code'] ?? 'AUD',
            countryCode: $data['country_code'] ?? 'AU', abn: $data['abn'] ?? null,
            gstRegistered: (bool) ($data['gst_registered'] ?? false),
        ));
        return response()->json(['data' => ['public_id' => $company->public_id, 'name' => $company->name, 'slug' => $company->slug]], 201);
    }
}
