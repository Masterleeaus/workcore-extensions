<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateStockCount implements BusinessActionHandlerContract
{
    public function __construct(private SupplyRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createStockCount($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('stock_count', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.supply.stock_count.created', 1, ['record' => $record])],
        );
    }
}
