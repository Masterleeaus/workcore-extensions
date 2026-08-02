<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CustomerRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\CustomerData;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Modules\CRM\Services\CrmDuplicateDetector;

final class CreateCustomer implements BusinessActionHandlerContract
{
    public function __construct(private CustomerRepositoryContract $repo, private CrmDuplicateDetector $duplicates) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $data = CustomerData::fromArray($request->payload);
        $candidates = $this->duplicates->customerCandidates($request->companyId, $data->email, $data->phone);
        $record = $this->repo->create($data, $request->companyId, $request->actorId);
        $record['duplicate_candidates'] = $candidates;
        $reference = new TypedReference('customer', (string) $record['public_id']);

        return new ActionHandlerResult(
            data: $record,
            aggregate: $reference,
            events: [new PendingDomainEvent('workcore.customer.created', 1, ['customer' => $record])],
        );
    }
}
