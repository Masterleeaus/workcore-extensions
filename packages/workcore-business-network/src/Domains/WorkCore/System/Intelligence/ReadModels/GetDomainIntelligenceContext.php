<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionAuthorizer;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceContextService;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRegistry;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRequest;

final readonly class GetDomainIntelligenceContext
{
    public function __construct(
        private DomainIntelligenceContextService $contexts,
        private DomainIntelligenceRegistry $registry,
        private PermissionResolverContract $permissions,
        private DomainActionAuthorizer $actionAuthorizer,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $domain, int $maxItems = 50): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.permissions.view')), 403);
        $adapter = $this->registry->require(trim($domain));
        abort_unless($this->permissions->allows($actorId, $companyId, $adapter->viewPermission()), 403);

        $context = $this->contexts->build($adapter->key(), new DomainIntelligenceRequest(
            companyId: $companyId,
            actorId: $actorId,
            maxItems: $maxItems,
        ));

        return $context->toArray($this->actionAuthorizer->allowed($adapter->actions(), $actorId, $companyId));
    }
}
