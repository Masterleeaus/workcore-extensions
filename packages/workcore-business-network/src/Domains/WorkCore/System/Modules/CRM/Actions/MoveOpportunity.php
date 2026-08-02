<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class MoveOpportunity implements BusinessActionHandlerContract
{
    public function __construct(private OpportunityRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) $request->payload['opportunity_public_id'];
        $record = $this->repository->move(
            $request->companyId,
            $publicId,
            $request->actorId,
            (string) $request->payload['stage_public_id'],
            (int) ($request->payload['position'] ?? 0),
        );
        return new ActionHandlerResult(
            $record,
            new TypedReference('opportunity', $publicId),
            [new PendingDomainEvent('workcore.opportunity.moved', 1, ['opportunity' => $record])],
        );
    }
}
