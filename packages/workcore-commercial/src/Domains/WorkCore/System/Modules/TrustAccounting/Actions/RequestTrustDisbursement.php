<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class RequestTrustDisbursement implements BusinessActionHandlerContract
{
    public function __construct(private TrustAccountingRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->requestDisbursement($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('trust_disbursement', (string) ($record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.trust.disbursement.requested', 1, ['record' => $record])],
        );
    }
}
