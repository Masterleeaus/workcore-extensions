<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Catalogue\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Catalogue\Contracts\ServiceCatalogueRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateServiceCategory implements BusinessActionHandlerContract{public function __construct(private ServiceCatalogueRepositoryContract $repository){} public function handle(ActionRequest $request):ActionHandlerResult{$record=$this->repository->createCategory($request->payload,$request->companyId,$request->actorId);return new ActionHandlerResult($record,new TypedReference('service_category',(string)$record['public_id']),[new PendingDomainEvent('workcore.service_category.created',1,['service_category'=>$record])]);}}
