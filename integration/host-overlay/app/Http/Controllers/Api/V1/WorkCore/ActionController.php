<?php

namespace App\Http\Controllers\Api\V1\WorkCore;

use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationGrantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function confirm(
        Request $request,
        string $action,
        BusinessActionRegistry $registry,
        ConfirmationGrantService $grants,
        EntitlementResolverContract $entitlements,
        PermissionResolverContract $permissions,
    ): JsonResponse {
        $validated = $request->validate([
            'payload' => ['sometimes', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
        ]);

        $user = $request->user();
        abort_if($user === null, 401, 'Authentication required.');
        $activeCompanyColumn = (string) config('workcore.identity.active_company_column', 'active_company_id');
        $companyId = (int) $user->getAttribute($activeCompanyColumn);
        $actorId = (int) $user->getAuthIdentifier();
        abort_if($companyId < 1, 409, 'An active WorkCore company is required.');

        $definition = $registry->get($action);
        abort_if($definition === null, 404, 'WorkCore action not found.');
        $requiresConfirmation = $definition->requiresConfirmation || in_array($definition->risk, ['high', 'critical'], true);
        abort_unless($requiresConfirmation, 422, 'This WorkCore action does not require confirmation.');
        abort_unless($entitlements->allows($companyId, $definition->capability), 403, 'The company is not entitled to this action.');
        if ($definition->permission !== null) {
            abort_unless($permissions->allows($actorId, $companyId, $definition->permission), 403, 'The actor is not permitted to confirm this action.');
        }

        $idempotencyKey = $validated['idempotency_key']
            ?? $request->header('Idempotency-Key', (string) Str::uuid());
        $actionRequest = new ActionRequest(
            key: $action,
            payload: $validated['payload'] ?? [],
            companyId: $companyId,
            actorId: $actorId,
            idempotencyKey: $idempotencyKey,
            source: 'workcore_api',
        );
        $confirmationId = $grants->issue(
            $companyId,
            $actorId,
            $action,
            $actionRequest->payloadHash(),
            $idempotencyKey,
        );

        return response()->json(['data' => [
            'confirmation_id' => $confirmationId,
            'idempotency_key' => $idempotencyKey,
            'action' => $action,
            'payload_hash' => $actionRequest->payloadHash(),
            'expires_in' => (int) config('workcore.intelligence.approvals.ttl_seconds', 300),
        ]], 201);
    }

    public function execute(Request $request, string $action, BusinessActionDispatcher $dispatcher): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['sometimes', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:190'],
            'confirmation_id' => ['nullable', 'string', 'max:2048'],
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
