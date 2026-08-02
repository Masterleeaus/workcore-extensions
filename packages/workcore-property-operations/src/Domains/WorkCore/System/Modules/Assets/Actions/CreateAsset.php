<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateAsset implements BusinessActionHandlerContract
{
    public function __construct(private AssetRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->createAsset($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(data: $record, aggregate: new TypedReference('asset', (string) $record['public_id']), events: [new PendingDomainEvent('workcore.asset.created', 1, ['asset' => $record])]);
    }
}
