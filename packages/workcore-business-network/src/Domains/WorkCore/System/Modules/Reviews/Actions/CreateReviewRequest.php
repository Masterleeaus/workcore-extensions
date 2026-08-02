<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Reviews\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Reviews\Contracts\ReviewRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class CreateReviewRequest implements BusinessActionHandlerContract { public function __construct(private ReviewRepositoryContract $repository){} public function handle(ActionRequest $request): ActionHandlerResult { $record=$this->repository->createRequest($request->payload,$request->companyId,$request->actorId); return new ActionHandlerResult($record,new TypedReference('review_request',(string)$record['public_id']),[new PendingDomainEvent('workcore.review.request.created',1,['record'=>$record])]); } }
