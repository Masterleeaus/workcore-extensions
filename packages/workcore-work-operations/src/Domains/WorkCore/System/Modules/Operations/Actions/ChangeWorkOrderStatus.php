<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\WorkOrderComplianceGate;

final class ChangeWorkOrderStatus implements BusinessActionHandlerContract
{
    public function __construct(
        private WorkOrderRepositoryContract $repository,
        private WorkOrderComplianceGate $tradeCompliance,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        if ((string) $request->payload['status'] === 'completed') {
            $this->tradeCompliance->assertReadyForCompletionByPublicId(
                $request->companyId,
                (string) $request->payload['work_order_public_id'],
            );
        }

        $record = $this->repository->changeStatus(
            workOrderPublicId: (string) $request->payload['work_order_public_id'],
            toStatus: (string) $request->payload['status'],
            reason: $request->payload['reason'] ?? null,
            companyId: $request->companyId,
            actorId: $request->actorId,
        );

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('work_order', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.work_order.status_changed', 1, [
                'work_order_public_id' => $record['public_id'],
                'status' => $record['status'] ?? null,
                'reason' => $request->payload['reason'] ?? null,
            ])],
        );
    }
}
