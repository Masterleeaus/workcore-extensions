<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class PostStockAdjustment implements BusinessActionHandlerContract
{
    public function __construct(private SupplyRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        if (trim((string) ($request->payload['adjustment_public_id'] ?? '')) === '') { throw new \InvalidArgumentException('adjustment_public_id is required.'); }
        $record = $this->repository->postAdjustment((string) ($request->payload['adjustment_public_id'] ?? ''), $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('stock_adjustment', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.supply.adjustment.posted', 1, ['record' => $record])],
        );
    }
}
