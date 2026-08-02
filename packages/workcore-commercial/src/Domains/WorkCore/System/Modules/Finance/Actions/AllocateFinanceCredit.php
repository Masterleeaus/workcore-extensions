<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinanceRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class AllocateFinanceCredit implements BusinessActionHandlerContract
{
    public function __construct(private FinanceRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $creditId = $this->required($request->payload, 'credit_note_id', 'credit_note_public_id');
        $invoiceId = $this->required($request->payload, 'invoice_id', 'invoice_public_id');
        $record = $this->repository->allocateCredit($creditId, $invoiceId, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('finance_credit_allocation', (string) ($record['allocation']['id'] ?? $record['allocation']['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.finance.credit.allocated', 1, ['record' => $record])],
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
