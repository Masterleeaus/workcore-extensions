<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Compliance\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Compliance\Contracts\ComplianceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateComplianceObligation implements BusinessActionHandlerContract { public function __construct(private ComplianceRepositoryContract $repository){} public function handle(ActionRequest $request): ActionHandlerResult { $record=$this->repository->createObligation($request->payload,$request->companyId,$request->actorId); return new ActionHandlerResult($record,new TypedReference('compliance_obligation',(string)$record['public_id']),[new PendingDomainEvent('workcore.compliance.obligation.created',1,['record'=>$record])]); } }
