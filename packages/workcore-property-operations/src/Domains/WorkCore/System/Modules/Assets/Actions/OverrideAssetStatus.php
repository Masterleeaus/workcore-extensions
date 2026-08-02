<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class OverrideAssetStatus implements BusinessActionHandlerContract
{
    public function __construct(private AssetRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $asset = (string) $request->payload['asset_public_id'];
        $payload = ['override' => true] + $request->payload;
        $record = $this->repository->changeStatus($asset, $payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('asset', $asset),
            events: [new PendingDomainEvent('workcore.asset.status_overridden', 1, [
                'asset' => $record,
                'target_status' => $payload['status'],
                'reason' => $payload['reason'] ?? null,
            ])],
        );
    }
}
