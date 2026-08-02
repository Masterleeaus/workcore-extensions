<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assurance\Contracts\AssuranceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class UpdateCorrectiveAction implements BusinessActionHandlerContract
{
    public function __construct(private AssuranceRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->updateCorrectiveAction((string) ($request->payload['corrective_action_public_id'] ?? ''), $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('corrective_action', (string) ($record['public_id'] ?? $record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.corrective_action.updated', 1, ['record' => $record])],
        );
    }
}
