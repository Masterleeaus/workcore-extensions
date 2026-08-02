<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateInventoryCategory implements BusinessActionHandlerContract
{
    public function __construct(private SupplyRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createCategory($request->payload, $request->companyId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('inventory_category', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.inventory.category.created', 1, ['record' => $record])],
        );
    }
}
