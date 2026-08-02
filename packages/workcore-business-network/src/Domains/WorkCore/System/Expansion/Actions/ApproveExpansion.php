<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class ApproveExpansion implements BusinessActionHandlerContract
{
    public function __construct(private ExpansionRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = trim((string) ($request->payload['request_public_id'] ?? ''));
        if ($publicId === '') {
            throw new InvalidArgumentException('Expansion request_public_id is required.');
        }
        $approved = $this->repository->approveRequest(
            $request->companyId,
            $publicId,
            $request->actorId,
            isset($request->payload['notes']) ? trim((string) $request->payload['notes']) : null,
        );

        return new ActionHandlerResult(
            data: $approved,
            aggregate: new TypedReference('capability_expansion_request', $publicId),
            events: [new PendingDomainEvent('workcore.capability.expansion_approved', 1, [
                'request_public_id' => $publicId,
                'delivery_mode' => $approved['delivery_mode'],
                'status' => $approved['status'],
            ])],
        );
    }
}
