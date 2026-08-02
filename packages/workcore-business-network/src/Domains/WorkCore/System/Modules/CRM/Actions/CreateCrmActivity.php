<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CrmActivityRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\CrmActivityData;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateCrmActivity implements BusinessActionHandlerContract
{
    public function __construct(private CrmActivityRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->create(CrmActivityData::fromArray($request->payload), $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            $record,
            new TypedReference('crm_activity', (string) $record['public_id']),
            [new PendingDomainEvent('workcore.crm_activity.created', 1, ['activity' => $record])],
        );
    }
}
