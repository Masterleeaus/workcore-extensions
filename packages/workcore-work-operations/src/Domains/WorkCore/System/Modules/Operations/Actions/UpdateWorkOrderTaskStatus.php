<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class UpdateWorkOrderTaskStatus implements BusinessActionHandlerContract
{
    public function __construct(private WorkOrderRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->updateTaskStatus(
            taskPublicId: (string) $request->payload['task_public_id'],
            toStatus: (string) $request->payload['status'],
            companyId: $request->companyId,
            actorId: $request->actorId,
        );

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('task', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.work_order_task.status_changed', 1, $record)],
        );
    }
}
