<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\WorkCore;

use App\Http\Controllers\Controller;
use App\Services\WorkCore\CustomerPropertyWorkOrderFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $result = $flow->execute(
            payload: $validated,
            companyId: (int) $user->active_company_id,
            actorId: (int) $user->getAuthIdentifier(),
            idempotencyKey: $validated['idempotency_key'] ?? $request->header('Idempotency-Key', (string) Str::uuid()),
            confirmationId: $validated['confirmation_id'],
        );

        return response()->json(['data' => $result], 201);
    }
}
