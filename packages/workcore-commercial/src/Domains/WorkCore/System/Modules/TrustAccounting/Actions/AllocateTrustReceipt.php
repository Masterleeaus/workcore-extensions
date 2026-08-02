<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class AllocateTrustReceipt implements BusinessActionHandlerContract
{
    public function __construct(private TrustAccountingRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $receipt = (string) ($request->payload['receipt_public_id'] ?? ''); $matter = (string) ($request->payload['matter_public_id'] ?? ''); if ($receipt === '' || $matter === '') throw new InvalidArgumentException('Receipt and matter are required.'); $record = $this->repository->allocateReceipt($receipt, $matter, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('trust_allocation', (string) ($record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.trust.receipt.allocated', 1, ['record' => $record])],
        );
    }
}
