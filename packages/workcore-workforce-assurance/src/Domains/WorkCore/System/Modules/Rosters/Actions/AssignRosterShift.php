<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class AssignRosterShift implements BusinessActionHandlerContract
{
    public function __construct(private RosterRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $shiftPublicId = (string) $request->payload['shift_public_id'];
        $record = $this->repository->assignShift($shiftPublicId, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record, new TypedReference('roster_shift', $shiftPublicId), [new PendingDomainEvent('workcore.roster_shift.assigned', 1, ['shift' => $record])]);
    }
}
