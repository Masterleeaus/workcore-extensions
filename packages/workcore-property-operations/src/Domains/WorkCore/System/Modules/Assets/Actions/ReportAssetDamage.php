<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class ReportAssetDamage implements BusinessActionHandlerContract
{
    public function __construct(private AssetRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $asset = (string) $request->payload['asset_public_id'];
        $record = $this->repository->reportDamage($asset, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('asset', $asset),
            events: [new PendingDomainEvent('workcore.asset.damage_reported', 1, ['asset_public_id' => $asset, 'damage_report' => $record])],
        );
    }
}
