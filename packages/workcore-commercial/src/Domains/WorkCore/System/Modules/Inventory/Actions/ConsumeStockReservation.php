<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Inventory\Contracts\InventoryRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class ConsumeStockReservation implements BusinessActionHandlerContract
{
    public function __construct(private InventoryRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $reservation = (string) ($request->payload['reservation_public_id'] ?? '');
        $record = $this->repository->consumeReservation($reservation, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('stock_reservation', $reservation),
            events: [new PendingDomainEvent('workcore.stock.reservation_consumed', 1, $record)],
        );
    }
}
