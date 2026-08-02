<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class RateSupplier implements BusinessActionHandlerContract
{
    public function __construct(private SupplyRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        if (trim((string) ($request->payload['supplier_public_id'] ?? '')) === '') { throw new \InvalidArgumentException('supplier_public_id is required.'); }
        $record = $this->repository->rateSupplier((string) ($request->payload['supplier_public_id'] ?? ''), array_diff_key($request->payload, ['supplier_public_id' => true]), $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('supplier_rating', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.supply.supplier.rated', 1, ['record' => $record])],
        );
    }
}
