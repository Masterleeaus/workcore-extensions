<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\Modules\Assets\Services\AssetStatusPolicy;
use App\Domains\WorkCore\System\References\TypedReference;

final class ChangeAssetStatus implements BusinessActionHandlerContract
{
    public function __construct(
        private AssetRepositoryContract $repository,
        private AssetStatusPolicy $policy,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $asset = (string) $request->payload['asset_public_id'];
        $record = $this->repository->changeStatus($asset, $request->payload, $request->companyId, $request->actorId);
        $event = $this->policy->eventFor((string) $request->payload['status']);

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('asset', $asset),
            events: [new PendingDomainEvent($event, 1, [
                'asset' => $record,
                'reason' => $request->payload['reason'] ?? null,
                'override' => (bool) ($request->payload['override'] ?? false),
            ])],
        );
    }
}
