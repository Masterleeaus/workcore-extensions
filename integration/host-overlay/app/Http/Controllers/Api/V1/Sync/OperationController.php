<?php

namespace App\Http\Controllers\Api\V1\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\OfflineOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OperationController extends Controller
{
    public function store(Request $request, OfflineOperationService $operations): JsonResponse
    {
        $validated = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'device_id' => ['required', 'string', 'max:190'],
            'action' => ['required', 'string', 'max:190'],
            'payload' => ['sometimes', 'array'],
            'occurred_at' => ['required', 'date'],
            'confirmation_id' => ['nullable', 'string', 'max:190'],
        ]);

        $user = $request->user();
        $response = $operations->execute(
            companyId: (int) $user->active_company_id,
            actorId: (int) $user->getAuthIdentifier(),
            operation: $validated,
        );

        return response()->json($response, $response['replayed'] ? 200 : 202);
    }

    public function show(Request $request, string $operationId): JsonResponse
    {
        $record = DB::table('workcore_sync_operations')
            ->where('company_id', (int) $request->user()->active_company_id)
            ->where('operation_id', $operationId)
            ->first();
        abort_if($record === null, 404, 'Sync operation not found.');

        return response()->json(['data' => [
            'operation_id' => $record->operation_id,
            'status' => $record->status,
            'action' => $record->action_key,
            'attempts' => (int) ($record->attempts ?? 0),
            'occurred_at' => $record->occurred_at,
            'available_at' => $record->available_at ?? null,
            'completed_at' => $record->completed_at,
            'result' => json_decode($record->result_json ?: '{}', true),
            'error' => $record->error_message,
        ]]);
    }
}
