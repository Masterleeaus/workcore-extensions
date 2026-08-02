<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\RecurringServices\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\RecurringServices\Contracts\RecurringServiceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class GenerateRecurringOccurrences implements BusinessActionHandlerContract { public function __construct(private RecurringServiceRepositoryContract $repository){} public function handle(ActionRequest $request): ActionHandlerResult { $record=$this->repository->generate($request->payload['agreement_public_id']??null,$request->payload,$request->companyId,$request->actorId); return new ActionHandlerResult($record,new TypedReference('recurring_generation',(string)$record['public_id']),[new PendingDomainEvent('workcore.recurring.occurrences.generated',1,['record'=>$record])]); } }
