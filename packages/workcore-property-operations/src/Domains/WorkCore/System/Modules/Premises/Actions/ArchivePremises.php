<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Contracts\PremisesRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;

final class ArchivePremises implements BusinessActionHandlerContract
{
    public function __construct(private PremisesRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $premises = (string) ($request->payload['premises_public_id'] ?? '');
        if ($premises === '') { throw new \InvalidArgumentException('premises_public_id is required.'); }
        $payload = $request->payload; unset($payload['premises_public_id']);
        $record = $this->repository->archive($premises, $payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record, new TypedReference('premises', $premises), [new PendingDomainEvent('workcore.premises.archived', 1, ['premises'=>$record,'reason'=>$payload['reason']??null])]);
    }
}
