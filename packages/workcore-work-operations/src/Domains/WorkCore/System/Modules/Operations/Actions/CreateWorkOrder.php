<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateWorkOrder implements BusinessActionHandlerContract
{
    public function __construct(private WorkOrderRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->create($request->payload, $request->companyId, $request->actorId);

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('work_order', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.work_order.created', 1, ['work_order' => $record])],
        );
    }
}
