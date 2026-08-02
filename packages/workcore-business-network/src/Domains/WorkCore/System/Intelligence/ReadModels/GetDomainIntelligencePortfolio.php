<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionAuthorizer;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceContextService;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRegistry;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRequest;

final readonly class GetDomainIntelligencePortfolio
{
    public function __construct(
        private DomainIntelligenceContextService $contexts,
        private DomainIntelligenceRegistry $registry,
        private PermissionResolverContract $permissions,
        private DomainActionAuthorizer $actionAuthorizer,
    ) {}

    /**
     * @param list<string> $domains
     * @return array<string,mixed>
     */
    public function execute(array $domains = []): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.permissions.view')), 403);

        $selected = $domains === [] ? array_keys($this->registry->all()) : array_values(array_unique(array_map('strval', $domains)));
        $allowed = [];
        foreach ($selected as $domain) {
            $adapter = $this->registry->require($domain);
            if ($this->permissions->allows($actorId, $companyId, $adapter->viewPermission())) {
                $allowed[] = $domain;
            }
        }
        $request = new DomainIntelligenceRequest($companyId, $actorId, maxItems: 50);
        $contexts = $this->contexts->buildMany($allowed, $request);
        $signalCounts = ['info' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        foreach ($contexts as $context) {
            foreach ($context->signals as $signal) {
                $signalCounts[$signal->severity]++;
            }
        }

        return [
            'company_id' => $companyId,
            'domains' => array_map(
                fn ($context): array => $context->toArray($this->actionAuthorizer->allowed(
                    $this->registry->require($context->domain)->actions(),
                    $actorId,
                    $companyId,
                )),
                $contexts,
            ),
            'signal_counts' => $signalCounts,
            'execution_mode' => 'proposal_only',
        ];
    }
}
