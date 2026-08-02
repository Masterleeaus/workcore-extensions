<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateRosterShift implements BusinessActionHandlerContract
{
    public function __construct(private RosterRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createShift($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record, new TypedReference('roster_shift', (string) $record['public_id']), [new PendingDomainEvent('workcore.roster_shift.created', 1, ['shift' => $record])]);
    }
}
