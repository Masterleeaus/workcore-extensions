<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\OpportunityData;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateOpportunity implements BusinessActionHandlerContract
{
    public function __construct(private OpportunityRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->create(OpportunityData::fromArray($request->payload), $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            $record,
            new TypedReference('opportunity', (string) $record['public_id']),
            [new PendingDomainEvent('workcore.opportunity.created', 1, ['opportunity' => $record])],
        );
    }
}
