<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexStatusContract;

final class GetKnowledgeDocumentStatus
{
    public function __construct(
        private KnowledgeIndexStatusContract $index,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed>|null */
    public function execute(string $documentId): ?array
    {
        $tenant = workcore_tenant();
        $companyId = $tenant->companyId();
        $actorId = $tenant->userId();
        if ($companyId === null || $actorId === null) {
            abort(403);
        }
        abort_unless($this->permissions->allows($actorId, $companyId, (string) config('workcore.intelligence.knowledge.permissions.search')), 403);

        return $this->index->status($companyId, trim($documentId));
    }
}
