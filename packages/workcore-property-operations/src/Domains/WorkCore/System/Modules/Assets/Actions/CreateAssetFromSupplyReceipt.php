<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateAssetFromSupplyReceipt implements BusinessActionHandlerContract
{
    public function __construct(private AssetRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createFromSupplyReceipt($request->payload, $request->companyId, $request->actorId);
        $asset = (string) ($record['asset']['public_id'] ?? '');
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('asset', $asset),
            events: [new PendingDomainEvent('workcore.asset.received', 1, ['asset' => $record['asset'] ?? [], 'handoff' => $record['handoff'] ?? [], 'replayed' => $record['replayed'] ?? false])],
        );
    }
}
