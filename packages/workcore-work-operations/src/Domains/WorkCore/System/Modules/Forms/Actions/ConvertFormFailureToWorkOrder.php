<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Forms\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Forms\Contracts\FormRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class ConvertFormFailureToWorkOrder implements BusinessActionHandlerContract
{
    public function __construct(private FormRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->convertFailureToWorkOrder((string) $request->payload['failure_public_id'], $request->payload, $request->companyId, $request->actorId);
        $workOrderId = (string) ($record['work_order']['public_id'] ?? '');
        return new ActionHandlerResult($record, $workOrderId !== '' ? new TypedReference('work_order', $workOrderId) : null, [new PendingDomainEvent('workcore.form_failure.converted_to_work_order', 1, $record)]);
    }
}
