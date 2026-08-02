<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionAuthorizer;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRegistry;

final readonly class ListDomainIntelligenceAdapters
{
    public function __construct(
        private DomainIntelligenceRegistry $registry,
        private PermissionResolverContract $permissions,
        private DomainActionAuthorizer $actionAuthorizer,
    ) {}

    /** @return list<array<string,mixed>> */
    public function execute(): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.permissions.view')), 403);

        $result = [];
        foreach ($this->registry->all() as $adapter) {
            if (! $this->permissions->allows($actorId, $companyId, $adapter->viewPermission())) {
                continue;
            }
            $result[] = [
                'key' => $adapter->key(),
                'name' => $adapter->name(),
                'capability' => $adapter->capability(),
                'view_permission' => $adapter->viewPermission(),
                'actions' => array_map(
                    static fn ($action): array => $action->toArray(),
                    $this->actionAuthorizer->allowed($adapter->actions(), $actorId, $companyId),
                ),
                'execution_mode' => 'proposal_only',
            ];
        }

        return $result;
    }
}
