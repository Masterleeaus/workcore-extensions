<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use RuntimeException;

final class ListAdaptiveFeatureRuns
{
    public function __construct(
        private AdaptiveFeatureRepositoryContract $features,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function execute(string $feature, ?string $status = null, int $limit = 100): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.expansion.permissions.features_view')), 403);
        $record = $this->features->findFeature($companyId, trim($feature))
            ?? throw new RuntimeException('Adaptive feature was not found.');
        return $this->features->listRuns($companyId, $record, $status, max(1, min($limit, 100)));
    }
}
