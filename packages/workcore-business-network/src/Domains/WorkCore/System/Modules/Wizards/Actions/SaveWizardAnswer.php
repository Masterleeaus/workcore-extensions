<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Wizards\Services\WizardRuntime;
use App\Domains\WorkCore\System\References\TypedReference;
final class SaveWizardAnswer implements BusinessActionHandlerContract
{
    public function __construct(private WizardRuntime $runtime) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record=$this->runtime->save((string)$request->payload['run_public_id'],(string)$request->payload['question_key'],$request->payload['value']??null,['source'=>$request->payload['source']??'user_entered','confidence'=>$request->payload['confidence']??null,'is_confirmed'=>$request->payload['is_confirmed']??null,'agent_public_id'=>$request->payload['agent_public_id']??null],$request->companyId,$request->actorId);
        return new ActionHandlerResult($record,new TypedReference('wizard_answer',(string)($record['public_id']??$record['run_public_id'])),[new PendingDomainEvent('workcore.wizard.answer.saved',1,['record'=>$record])]);
    }
}
