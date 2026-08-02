<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api;

use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class ApiResponseFactory
{
    public function __construct(private OperationContextContract $context) {}

    /** @param array<string,mixed>|array<int,mixed> $data @param array<string,mixed> $meta */
    public function success(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => [],
            'correlation_id' => $this->correlationId(),
        ], $status);
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $transform */
    public function paginated(LengthAwarePaginator $paginator, callable $transform): JsonResponse
    {
        $items = array_map($transform, array_map(static fn ($item): array => (array) $item, $paginator->items()));

        return $this->success($items, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function action(ActionResult $result, int $createdStatus = 201): JsonResponse
    {
        return response()->json([
            'data' => $result->data,
            'meta' => [
                'action' => $result->actionKey,
                'audit_id' => $result->auditId,
                'event_ids' => $result->eventIds,
                'replayed' => $result->replayed,
            ],
            'errors' => [],
            'correlation_id' => $result->correlationId,
        ], $result->replayed ? 200 : $createdStatus);
    }

    private function correlationId(): ?string
    {
        return $this->context->hasContext() ? $this->context->correlationId() : null;
    }
}
