<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class UpdateOpportunity implements BusinessActionHandlerContract
{
    public function __construct(private OpportunityRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) $request->payload['opportunity_public_id'];
        $payload = $request->payload;
        unset($payload['opportunity_public_id']);
        $record = $this->repository->update($request->companyId, $publicId, $request->actorId, $payload);
        return new ActionHandlerResult(
            $record,
            new TypedReference('opportunity', $publicId),
            [new PendingDomainEvent('workcore.opportunity.updated', 1, ['opportunity' => $record])],
        );
    }
}
