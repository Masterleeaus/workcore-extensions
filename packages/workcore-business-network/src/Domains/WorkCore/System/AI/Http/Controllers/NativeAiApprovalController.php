<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Http\Controllers;

use App\Domains\WorkCore\System\AI\Orchestration\ApprovalService;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use Illuminate\Http\{JsonResponse,Request};

final class NativeAiApprovalController
{
    public function decide(string $approval, Request $request, ApprovalService $service, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $tenant = workcore_tenant();
        $record = $service->decide(
            (int) $tenant->companyId(),
            (int) $tenant->userId(),
            $approval,
            (string) $validated['decision'],
            isset($validated['note']) ? (string) $validated['note'] : null,
        );
        return $api->success($record->toArray());
    }
}
