<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assurance\Contracts\AssuranceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class StartInspection implements BusinessActionHandlerContract
{
    public function __construct(private AssuranceRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->startInspection($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('inspection', (string) ($record['public_id'] ?? $record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.inspection.started', 1, ['record' => $record])],
        );
    }
}
