<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureSimulator;
use RuntimeException;

final class SimulateAdaptiveFeature
{
    public function __construct(
        private AdaptiveFeatureRepositoryContract $features,
        private AdaptiveFeatureSimulator $simulator,
        private PermissionResolverContract $permissions,
    ) {}

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function execute(string $feature, array $context, int $executionDepth = 0): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.features_simulate')), 403);
        $record = $this->features->findFeature($companyId, trim($feature))
            ?? throw new RuntimeException('Adaptive feature was not found.');
        $context['actor'] = array_replace(['id' => (string) $actorId], (array) ($context['actor'] ?? []));
        return $this->simulator->simulate((array) $record['blueprint'], $context, max(0, $executionDepth));
    }
}
