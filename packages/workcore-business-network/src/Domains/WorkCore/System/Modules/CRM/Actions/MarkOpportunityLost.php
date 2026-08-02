<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class MarkOpportunityLost implements BusinessActionHandlerContract
{
    public function __construct(private OpportunityRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) $request->payload['opportunity_public_id'];
        $reason = isset($request->payload['reason']) ? trim((string) $request->payload['reason']) : null;
        $record = $this->repository->markLost($request->companyId, $publicId, $request->actorId, $reason === '' ? null : $reason);
        return new ActionHandlerResult(
            $record,
            new TypedReference('opportunity', $publicId),
            [new PendingDomainEvent('workcore.opportunity.lost', 1, ['opportunity' => $record])],
        );
    }
}
