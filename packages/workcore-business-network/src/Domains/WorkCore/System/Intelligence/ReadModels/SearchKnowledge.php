<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeContextBuilder;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexContract;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeQuery;

final class SearchKnowledge
{
    public function __construct(
        private KnowledgeIndexContract $index,
        private KnowledgeContextBuilder $contextBuilder,
        private PermissionResolverContract $permissions,
    ) {}

    /** @param array<string,mixed> $filters @param list<string> $visibilities @return array<string,mixed> */
    public function execute(string $query, array $filters = [], int $limit = 8, array $visibilities = []): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.knowledge.permissions.search')), 403);

        $configuredVisibilities = array_values(array_unique(array_map('strval', (array) config('workcore.intelligence.knowledge.allowed_visibilities', ['company', 'private']))));
        $allowedVisibilities = $visibilities === []
            ? $configuredVisibilities
            : array_values(array_intersect($configuredVisibilities, array_map('strval', $visibilities)));
        if ($allowedVisibilities === []) {
            abort(403);
        }

        $allowedPermissions = [];
        foreach ((array) config('workcore.intelligence.knowledge.permission_candidates', []) as $permission) {
            $permission = (string) $permission;
            if ($permission !== '' && $this->permissions->allows($actorId, $companyId, $permission)) {
                $allowedPermissions[] = $permission;
            }
        }

        $retrieval = $this->index->search(new KnowledgeQuery(
            companyId: $companyId,
            actorId: $actorId,
            query: $query,
            allowedVisibilities: $allowedVisibilities,
            filters: $filters,
            limit: $limit,
            allowedPermissions: $allowedPermissions,
        ));
        $result = $retrieval->toArray();
        $result['context_window'] = $this->contextBuilder->build(
            $retrieval,
            (int) config('workcore.intelligence.knowledge.context_max_tokens', 4000),
        )->toArray();

        return $result;
    }
}
