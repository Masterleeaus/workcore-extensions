<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class DisableAdaptiveFeature implements BusinessActionHandlerContract
{
    public function __construct(private AdaptiveFeatureRepositoryContract $features) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $identifier = trim((string) ($request->payload['feature'] ?? ''));
        $reason = trim((string) ($request->payload['reason'] ?? 'Disabled by an authorised operator.'));
        if ($identifier === '') {
            throw new InvalidArgumentException('Adaptive feature identifier is required.');
        }
        $feature = $this->features->disableFeature($request->companyId, $identifier, $request->actorId, $reason);

        return new ActionHandlerResult(
            data: $feature,
            aggregate: new TypedReference('adaptive_feature', (string) $feature['public_id']),
            events: [new PendingDomainEvent('workcore.adaptive_feature.disabled', 1, [
                'feature_public_id' => $feature['public_id'],
                'reason' => $reason,
            ])],
        );
    }
}
