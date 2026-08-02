<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Wizards\Contracts\WizardRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateWizardDefinition implements BusinessActionHandlerContract { public function __construct(private WizardRepositoryContract $repository){} public function handle(ActionRequest $request): ActionHandlerResult { $r=$this->repository->createDefinition($request->payload,$request->companyId,$request->actorId); return new ActionHandlerResult($r,new TypedReference('wizard_definition',$r['public_id']),[new PendingDomainEvent('workcore.wizard.definition.created',1,['record'=>$r])]); } }
