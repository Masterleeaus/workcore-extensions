<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\SalesPipelineRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\SalesPipelineData;
use App\Domains\WorkCore\System\References\TypedReference;

final class CreateSalesPipeline implements BusinessActionHandlerContract
{
    public function __construct(private SalesPipelineRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->create(SalesPipelineData::fromArray($request->payload), $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            $record,
            new TypedReference('sales_pipeline', (string) $record['public_id']),
            [new PendingDomainEvent('workcore.sales_pipeline.created', 1, ['pipeline' => $record])],
        );
    }
}
