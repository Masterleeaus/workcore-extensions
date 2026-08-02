<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureDomainCompatibilityValidator;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;
use RuntimeException;

final class ActivateAdaptiveFeature implements BusinessActionHandlerContract
{
    public function __construct(
        private ExpansionRepositoryContract $expansion,
        private AdaptiveFeatureRepositoryContract $features,
        private AdaptiveFeatureBlueprintValidator $blueprints,
        private AdaptiveFeatureDomainCompatibilityValidator $compatibility,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $requestPublicId = trim((string) ($request->payload['request_public_id'] ?? ''));
        if ($requestPublicId === '') {
            throw new InvalidArgumentException('Expansion request_public_id is required.');
        }
        $expansion = $this->expansion->findRequest($request->companyId, $requestPublicId)
            ?? throw new RuntimeException('Capability expansion request was not found.');
        if (($expansion['delivery_mode'] ?? null) !== 'adaptive_feature') {
            throw new RuntimeException('Only adaptive-feature expansion requests can activate a declarative feature.');
        }
        if (! in_array((string) ($expansion['status'] ?? ''), ['approved', 'activated'], true)) {
            throw new RuntimeException('Adaptive-feature expansion must be approved before activation.');
        }
        if (($expansion['status'] ?? null) === 'activated') {
            $existing = $this->features->findFeatureByRequest($request->companyId, $requestPublicId);
            if ($existing !== null) {
                return new ActionHandlerResult($existing, new TypedReference('adaptive_feature', (string) $existing['public_id']));
            }
        }

        $blueprint = $this->blueprints->validate((array) ($expansion['plan']['blueprint'] ?? []));
        $domain = null;
        if (($blueprint['trigger']['subject_type'] ?? null) === 'adaptive_record') {
            $domainIdentifier = trim((string) ($blueprint['trigger']['domain'] ?? ''));
            $domain = $this->expansion->findAdaptiveDomain($request->companyId, $domainIdentifier, true)
                ?? throw new RuntimeException('Adaptive feature target domain is not active or was not found.');
            $this->compatibility->validate($blueprint, (array) ($domain['blueprint'] ?? []));
        }

        $feature = $this->features->activateFeature($request->companyId, $requestPublicId, $request->actorId, $blueprint, $domain);
        $this->expansion->markRequestStatus($request->companyId, $requestPublicId, 'activated');

        return new ActionHandlerResult(
            data: $feature,
            aggregate: new TypedReference('adaptive_feature', (string) $feature['public_id']),
            events: [new PendingDomainEvent('workcore.adaptive_feature.activated', 1, [
                'request_public_id' => $requestPublicId,
                'feature_public_id' => $feature['public_id'],
                'trigger_event' => $feature['trigger_event'],
                'domain_key' => $feature['domain_key'],
            ])],
        );
    }
}
