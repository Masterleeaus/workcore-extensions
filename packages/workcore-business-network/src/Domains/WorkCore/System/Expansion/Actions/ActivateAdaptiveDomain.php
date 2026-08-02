<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveDomainBlueprintValidator;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;
use RuntimeException;

final class ActivateAdaptiveDomain implements BusinessActionHandlerContract
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private AdaptiveDomainBlueprintValidator $blueprints,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = trim((string) ($request->payload['request_public_id'] ?? ''));
        if ($publicId === '') {
            throw new InvalidArgumentException('Expansion request_public_id is required.');
        }
        $expansion = $this->repository->findRequest($request->companyId, $publicId)
            ?? throw new RuntimeException('Capability expansion request was not found.');
        if (($expansion['delivery_mode'] ?? null) !== 'adaptive_domain') {
            throw new RuntimeException('Only adaptive-domain expansion requests can be activated without a code deployment.');
        }
        if (! in_array((string) $expansion['status'], ['approved', 'activated'], true)) {
            throw new RuntimeException('Adaptive-domain expansion must be approved before activation.');
        }
        if ($expansion['status'] === 'activated') {
            $existing = $this->repository->findAdaptiveDomainByRequest($request->companyId, $publicId);
            if ($existing !== null) {
                return new ActionHandlerResult($existing, new TypedReference('adaptive_domain', (string) $existing['public_id']));
            }
        }

        $blueprint = $this->blueprints->validate((array) ($expansion['plan']['blueprint'] ?? []));
        $domain = $this->repository->activateAdaptiveDomain($request->companyId, $publicId, $request->actorId, $blueprint);

        return new ActionHandlerResult(
            data: $domain,
            aggregate: new TypedReference('adaptive_domain', (string) $domain['public_id']),
            events: [new PendingDomainEvent('workcore.adaptive_domain.activated', 1, [
                'request_public_id' => $publicId,
                'domain_public_id' => $domain['public_id'],
                'slug' => $domain['slug'],
                'capability_key' => $domain['capability_key'],
            ])],
        );
    }
}
