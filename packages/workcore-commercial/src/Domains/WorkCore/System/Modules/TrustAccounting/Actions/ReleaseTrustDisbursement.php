<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class ReleaseTrustDisbursement implements BusinessActionHandlerContract
{
    public function __construct(private TrustAccountingRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) ($request->payload['disbursement_public_id'] ?? ''); if ($publicId === '') throw new InvalidArgumentException('Disbursement is required.'); $record = $this->repository->releaseDisbursement($publicId, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('trust_disbursement', (string) ($record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.trust.disbursement.released', 1, ['record' => $record])],
        );
    }
}
