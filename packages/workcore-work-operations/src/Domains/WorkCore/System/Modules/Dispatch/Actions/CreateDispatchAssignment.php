<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Contracts\DispatchRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateDispatchAssignment implements BusinessActionHandlerContract
{
    public function __construct(private DispatchRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->create($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('dispatch_assignment', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.dispatch.assigned', 1, ['dispatch' => $record])],
        );
    }
}
