<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Forms\Actions;
use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Forms\Contracts\FormRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
final class SubmitFormSubmission implements BusinessActionHandlerContract
{
    public function __construct(private FormRepositoryContract $repository) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $record = $this->repository->submitSubmission((string) $request->payload['submission_public_id'], $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult($record, new TypedReference('form_submission', (string) $record['public_id']), [new PendingDomainEvent('workcore.form_submission.submitted', 1, ['submission' => $record])]);
    }
}
