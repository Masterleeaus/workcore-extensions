<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexContract;

final class GetIntelligenceStatus
{
    public function __construct(
        private KnowledgeIndexContract $knowledge,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(): array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.permissions.view')), 403);

        return [
            'company_id' => $companyId,
            'enabled' => (bool) config('workcore.intelligence.enabled', true),
            'safety_enabled' => (bool) config('workcore.intelligence.safety.enabled', true),
            'bound_ai_approvals' => (bool) config('workcore.intelligence.approvals.enabled', true),
            'knowledge_index' => $this->knowledge::class,
            'knowledge_operational' => $this->knowledge instanceof \App\Domains\WorkCore\System\Intelligence\Knowledge\DatabaseKnowledgeIndex,
            'knowledge_tenant_filter_required' => (bool) config('workcore.intelligence.knowledge.tenant_filter_required', true),
            'knowledge_permission_filter_required' => (bool) config('workcore.intelligence.knowledge.permission_filter_required', true),
            'knowledge_freshness_days' => (int) config('workcore.intelligence.knowledge.stale_after_days', 90),
            'learning_mode' => (string) config('workcore.intelligence.learning.mode', 'review_only'),
            'runtime_code_generation' => false,
        ];
    }
}
