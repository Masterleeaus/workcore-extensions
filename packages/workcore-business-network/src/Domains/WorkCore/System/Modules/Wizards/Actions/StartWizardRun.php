<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Wizards\Services\WizardRuntime;
use App\Domains\WorkCore\System\References\TypedReference;
final class StartWizardRun implements BusinessActionHandlerContract
{
    public function __construct(private WizardRuntime $runtime) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->runtime->start((string)$request->payload['definition_key'],$request->payload,$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('wizard_run',(string)($record['public_id']??$record['run_public_id'])),[new PendingDomainEvent('workcore.wizard.run.started',1,['record'=>$record])]);
    }
}
