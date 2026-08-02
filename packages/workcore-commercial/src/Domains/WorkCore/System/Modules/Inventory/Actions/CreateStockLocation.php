<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Inventory\Contracts\InventoryRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateStockLocation implements BusinessActionHandlerContract
{
    public function __construct(private InventoryRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createLocation($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('stock_location', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.stock_location.created', 1, ['stock_location' => $record])],
        );
    }
}
