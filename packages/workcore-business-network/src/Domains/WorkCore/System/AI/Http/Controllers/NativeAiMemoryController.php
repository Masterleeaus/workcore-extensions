<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Http\Controllers;

use App\Domains\WorkCore\System\AI\Orchestration\MemoryService;
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use Illuminate\Http\{JsonResponse,Request};

final class NativeAiMemoryController
{
    public function index(Request $request, MemoryService $service, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $items = $service->list((int) $tenant->companyId(), (int) $tenant->userId(), $request->integer('limit', 100));
        return $api->success(array_map(static fn ($item): array => $item->toArray(), $items));
    }

    public function store(Request $request, MemoryService $service, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:user,company'],
            'key' => ['required', 'string', 'max:160'],
            'value' => ['required', 'string', 'max:10000'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'agent_public_id' => ['nullable', 'string', 'size:26'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $tenant = workcore_tenant();
        $item = $service->remember(
            (int) $tenant->companyId(),
            (int) $tenant->userId(),
            $validated['agent_public_id'] ?? null,
            (string) $validated['scope'],
            (string) $validated['key'],
            (string) $validated['value'],
            (float) ($validated['confidence'] ?? 1.0),
            isset($validated['expires_at']) ? (string) $validated['expires_at'] : null,
        );
        return $api->success($item->toArray(), status: 201);
    }

    public function destroy(string $memory, MemoryService $service, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        if (! $service->forget((int) $tenant->companyId(), (int) $tenant->userId(), $memory)) {
            throw new AiOrchestrationException('The AI memory was not found.', 404);
        }
        return $api->success(['public_id' => $memory, 'deleted' => true]);
    }
}
