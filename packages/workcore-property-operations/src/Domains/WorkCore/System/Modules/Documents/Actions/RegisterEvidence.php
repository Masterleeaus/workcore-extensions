<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Documents\Contracts\DocumentRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class RegisterEvidence implements BusinessActionHandlerContract
{
    public function __construct(private DocumentRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->registerEvidence($request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('evidence', (string) ($record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.evidence.registered', 1, ['record' => $record])],
        );
    }
}
