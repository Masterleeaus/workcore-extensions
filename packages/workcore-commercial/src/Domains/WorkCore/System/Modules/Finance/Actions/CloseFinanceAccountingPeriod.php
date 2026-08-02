<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinanceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class CloseFinanceAccountingPeriod implements BusinessActionHandlerContract
{
    public function __construct(private FinanceRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $id = $this->required($request->payload, 'accounting_period_id', 'accounting_period_public_id');
        $record = $this->repository->closeAccountingPeriod($id, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('finance_accounting_period', (string) ($record['id'] ?? $record['public_id'] ?? $record['invoice']['id'] ?? $record['quote']['id'] ?? '')),
            events: [new PendingDomainEvent('workcore.finance.period.closed', 1, ['record' => $record])],
        );
    }

    private function required(array $payload, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') return $value;
        }
        throw new InvalidArgumentException($keys[0].' is required.');
    }
}
