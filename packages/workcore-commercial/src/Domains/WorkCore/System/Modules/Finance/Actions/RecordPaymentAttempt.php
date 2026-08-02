<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentOrchestrationRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class RecordPaymentAttempt implements BusinessActionHandlerContract
{
    public function __construct(private PaymentOrchestrationRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $sessionId = trim((string) ($request->payload['session_id'] ?? $request->payload['payment_session_id'] ?? ''));
        if ($sessionId === '') {
            throw new InvalidArgumentException('session_id is required.');
        }
        $payload = $request->payload;
        $payload['session_id'] = $sessionId;
        $record = $this->repository->recordAttempt($payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('payment_attempt', (string) ($record['id'] ?? $record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.payment.attempt.recorded', 1, ['record' => $record])],
        );
    }
}
