<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateAvailabilityRule implements BusinessActionHandlerContract
{
    public function __construct(private RosterRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createAvailabilityRule($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record, new TypedReference('availability_rule', (string) $record['public_id']), [new PendingDomainEvent('workcore.worker_availability.created', 1, ['availability_rule' => $record])]);
    }
}
