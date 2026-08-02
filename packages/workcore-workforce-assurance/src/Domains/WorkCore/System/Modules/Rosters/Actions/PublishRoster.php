<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class PublishRoster implements BusinessActionHandlerContract
{
    public function __construct(private RosterRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $rosterPublicId = (string) $request->payload['roster_public_id'];
        $record = $this->repository->publishRoster($rosterPublicId, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record, new TypedReference('roster', $rosterPublicId), [new PendingDomainEvent('workcore.roster.status_changed', 1, ['roster' => $record])]);
    }
}
