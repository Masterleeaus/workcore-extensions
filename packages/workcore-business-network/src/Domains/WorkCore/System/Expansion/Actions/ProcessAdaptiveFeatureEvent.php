<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureEventProcessor;
use InvalidArgumentException;

final class ProcessAdaptiveFeatureEvent implements BusinessActionHandlerContract
{
    public function __construct(private AdaptiveFeatureEventProcessor $processor) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        if (! in_array($request->source, ['internal', 'event', 'outbox', 'scheduler', 'adaptive_feature'], true)) {
            throw new InvalidArgumentException('Adaptive feature event processing is internal-only.');
        }
        $event = trim((string) ($request->payload['event'] ?? ''));
        $subjectType = trim((string) ($request->payload['subject_type'] ?? ''));
        $subjectPublicId = trim((string) ($request->payload['subject_public_id'] ?? ''));
        if ($event === '' || $subjectType === '' || $subjectPublicId === '') {
            throw new InvalidArgumentException('Event, subject_type and subject_public_id are required.');
        }
        $result = $this->processor->process(
            $request->companyId,
            $request->actorId,
            $event,
            $subjectType,
            $subjectPublicId,
            (array) ($request->payload['context'] ?? []),
            max(0, (int) ($request->payload['execution_depth'] ?? 0)),
        );
        return new ActionHandlerResult(['runs' => $result['runs']], events: $result['events']);
    }
}
