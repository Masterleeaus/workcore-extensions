<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class AddTimeEntry implements BusinessActionHandlerContract
{
    public function __construct(private WorkOrderRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->addTimeEntry(
            workOrderPublicId: (string) $request->payload['work_order_public_id'],
            data: $request->payload,
            companyId: $request->companyId,
            actorId: $request->actorId,
        );

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('work_order', (string) $request->payload['work_order_public_id']),
            events: [new PendingDomainEvent('workcore.time_entry.created', 1, [
                'work_order_public_id' => $request->payload['work_order_public_id'],
                'time_entry' => $record,
            ])],
        );
    }
}
