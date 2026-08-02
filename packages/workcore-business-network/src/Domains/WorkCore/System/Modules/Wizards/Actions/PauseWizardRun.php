<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Wizards\Contracts\WizardRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class PauseWizardRun implements BusinessActionHandlerContract
{
    public function __construct(private WizardRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->repository->changeStatus((string)$request->payload['run_public_id'],'paused',$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('wizard_run',(string)($record['public_id']??$record['run_public_id'])),[new PendingDomainEvent('workcore.wizard.run.paused',1,['record'=>$record])]);
    }
}
