<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\CRM\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent}; use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract; use App\Domains\WorkCore\System\Modules\CRM\Services\LeadLifecycleService; use App\Domains\WorkCore\System\References\TypedReference;
final class UpdateLead implements BusinessActionHandlerContract { public function __construct(private LeadLifecycleService $service){} public function handle(ActionRequest $request): ActionHandlerResult { $id=(string)$request->payload['lead_public_id']; $data=$request->payload; unset($data['lead_public_id']); $record=$this->service->update($request->companyId,$id,$request->actorId,$data); return new ActionHandlerResult($record,new TypedReference('lead',$id),[new PendingDomainEvent('workcore.lead.updated',1,['lead'=>$record])]); } }
