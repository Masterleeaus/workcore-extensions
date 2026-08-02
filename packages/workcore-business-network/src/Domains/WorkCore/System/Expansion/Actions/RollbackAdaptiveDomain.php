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
use RuntimeException;

final class RollbackAdaptiveDomain implements BusinessActionHandlerContract
{
    public function __construct(private ExpansionRepositoryContract $repository) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $identifier = trim((string) ($request->payload['domain'] ?? ''));
        $version = (int) ($request->payload['version'] ?? 0);
        if ($identifier === '' || $version < 1) {
            throw new InvalidArgumentException('Adaptive domain identifier and positive version are required.');
        }
        $domain = $this->repository->findAdaptiveDomain($request->companyId, $identifier)
            ?? throw new RuntimeException('Adaptive domain was not found.');
        $fromVersion = (int) ($domain['version'] ?? 0);
        $restored = $this->repository->restoreAdaptiveDomainVersion(
            $request->companyId,
            $request->actorId,
            $domain,
            $version,
        );

        return new ActionHandlerResult(
            data: $restored,
            aggregate: new TypedReference('adaptive_domain', (string) $restored['public_id']),
            events: [new PendingDomainEvent('workcore.adaptive_domain.rolled_back', 1, [
                'domain_public_id' => $restored['public_id'],
                'from_version' => $fromVersion,
                'restored_from_version' => $version,
                'new_version' => $restored['version'],
            ])],
        );
    }
}
