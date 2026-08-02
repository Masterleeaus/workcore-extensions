<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Compliance\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Compliance\Contracts\ComplianceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class ChangeSafetyIncidentStatus implements BusinessActionHandlerContract { public function __construct(private ComplianceRepositoryContract $repository){} public function handle(ActionRequest $request): ActionHandlerResult { $record=$this->repository->changeIncidentStatus((string)$request->payload['incident_public_id'],$request->payload,$request->companyId,$request->actorId); return new ActionHandlerResult($record,new TypedReference('safety_incident',(string)$record['public_id']),[new PendingDomainEvent('workcore.safety.incident.status_changed',1,['record'=>$record])]); } }
