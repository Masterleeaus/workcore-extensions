<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Contracts\DispatchRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class ChangeDispatchStatus implements BusinessActionHandlerContract
{
    public function __construct(private DispatchRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->changeStatus(
            (string) $request->payload['dispatch_public_id'],
            (string) $request->payload['status'],
            $request->payload['reason'] ?? null,
            $request->payload,
            $request->companyId,
            $request->actorId,
        );
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('dispatch_assignment', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.dispatch.status_changed', 1, [
                'dispatch_public_id' => $record['public_id'],
                'status' => $record['status'] ?? null,
                'reason' => $request->payload['reason'] ?? null,
                'latitude' => $request->payload['latitude'] ?? null,
                'longitude' => $request->payload['longitude'] ?? null,
            ])],
        );
    }
}
