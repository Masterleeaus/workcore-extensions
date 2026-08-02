<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Http\Controllers;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

final class ActionController extends Controller
{
    public function index(BusinessActionRegistry $registry): JsonResponse
    {
        $actions = array_map(static fn ($definition): array => [
            'key' => $definition->key,
            'risk' => $definition->risk,
            'requires_confirmation' => $definition->requiresConfirmation,
            'capability' => $definition->capability,
            'permission' => $definition->permission,
            'metadata' => $definition->metadata,
        ], array_values($registry->all()));

        return response()->json(['data' => $actions, 'count' => count($actions)]);
    }

    public function show(string $action, BusinessActionRegistry $registry): JsonResponse
    {
        $definition = $registry->get($action);
        abort_if($definition === null, 404, 'WorkCore action not found.');

        return response()->json(['data' => [
            'key' => $definition->key,
            'risk' => $definition->risk,
            'requires_confirmation' => $definition->requiresConfirmation,
            'capability' => $definition->capability,
            'permission' => $definition->permission,
            'metadata' => $definition->metadata,
        ]]);
    }

    public function execute(Request $request, string $action, BusinessActionDispatcher $dispatcher): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['sometimes', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
            'confirmation_id' => ['nullable', 'string', 'max:190'],
        ]);

        $user = $request->user();
        abort_if($user === null, 401, 'Authentication required.');
        $activeCompanyColumn = (string) config('workcore.identity.active_company_column', 'active_company_id');
        $companyId = (int) $user->getAttribute($activeCompanyColumn);
        abort_if($companyId < 1, 409, 'An active WorkCore company is required.');

        $result = $dispatcher->dispatch(new ActionRequest(
            key: $action,
            payload: $validated['payload'] ?? [],
            companyId: $companyId,
            actorId: (int) $user->getAuthIdentifier(),
            idempotencyKey: $validated['idempotency_key'] ?? $request->header('Idempotency-Key', (string) Str::uuid()),
            confirmationId: $validated['confirmation_id'] ?? $request->header('X-Confirmation-ID'),
            source: 'workcore_api',
        ));

        return response()->json(['data' => $result->toArray()]);
    }
}
