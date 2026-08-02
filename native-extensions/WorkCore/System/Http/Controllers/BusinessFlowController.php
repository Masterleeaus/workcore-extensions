<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Http\Controllers;

use App\Extensions\WorkCore\System\Services\CustomerPropertyWorkOrderFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

final class BusinessFlowController extends Controller
{
    public function customerPropertyWorkOrder(Request $request, CustomerPropertyWorkOrderFlow $flow): JsonResponse
    {
        $validated = $request->validate([
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'property' => ['required', 'array'],
            'property.name' => ['required', 'string', 'max:255'],
            'property.address_line_1' => ['required', 'string', 'max:255'],
            'work_order' => ['required', 'array'],
            'work_order.title' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:160'],
            'confirmation_id' => ['required', 'string', 'max:190'],
        ]);

        $user = $request->user();
        abort_if($user === null, 401, 'Authentication required.');
        $activeCompanyColumn = (string) config('workcore.identity.active_company_column', 'active_company_id');
        $companyId = (int) $user->getAttribute($activeCompanyColumn);
        abort_if($companyId < 1, 409, 'An active WorkCore company is required.');

        $result = $flow->execute(
            payload: $validated,
            companyId: $companyId,
            actorId: (int) $user->getAuthIdentifier(),
            idempotencyKey: $validated['idempotency_key'] ?? $request->header('Idempotency-Key', (string) Str::uuid()),
            confirmationId: $validated['confirmation_id'],
        );

        return response()->json(['data' => $result], 201);
    }
}
