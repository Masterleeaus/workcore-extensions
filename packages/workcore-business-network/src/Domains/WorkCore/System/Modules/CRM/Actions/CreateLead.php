<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\LeadRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\LeadData;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Modules\CRM\Services\CrmDuplicateDetector;

final class CreateLead implements BusinessActionHandlerContract
{
    public function __construct(private LeadRepositoryContract $repo, private CrmDuplicateDetector $duplicates) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $data = LeadData::fromArray($request->payload);
        $candidates = $this->duplicates->leadCandidates($request->companyId, $data->email, $data->phone);
        $record = $this->repo->create($data, $request->companyId, $request->actorId);
        $record['duplicate_candidates'] = $candidates;
        $reference = new TypedReference('lead', (string) $record['public_id']);

        return new ActionHandlerResult(
            data: $record,
            aggregate: $reference,
            events: [new PendingDomainEvent('workcore.lead.created', 1, ['lead' => $record])],
        );
    }
}
