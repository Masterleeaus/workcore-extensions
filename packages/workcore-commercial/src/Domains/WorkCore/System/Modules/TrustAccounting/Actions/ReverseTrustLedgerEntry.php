<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts\TrustAccountingRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class ReverseTrustLedgerEntry implements BusinessActionHandlerContract
{
    public function __construct(private TrustAccountingRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) ($request->payload['ledger_entry_public_id'] ?? ''); if ($publicId === '') throw new InvalidArgumentException('Ledger entry is required.'); $record = $this->repository->reverseLedgerEntry($publicId, $request->payload, $request->companyId, $request->actorId);
        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('trust_ledger_entry', (string) ($record['public_id'] ?? '')),
            events: [new PendingDomainEvent('workcore.trust.ledger.reversed', 1, ['record' => $record])],
        );
    }
}
