<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final readonly class KnowledgeQuery
{
    /** @param list<string> $allowedVisibilities @param array<string,mixed> $filters @param list<string> $allowedPermissions */
    public function __construct(
        public int $companyId,
        public int $actorId,
        public string $query,
        public array $allowedVisibilities,
        public array $filters = [],
        public int $limit = 8,
        public array $allowedPermissions = [],
    ) {
        if ($companyId < 1 || $actorId < 1 || strlen(trim($query)) < 2 || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Knowledge query is invalid.');
        }

        new KnowledgeAccessScope($companyId, $actorId, $allowedVisibilities, $allowedPermissions);

        $allowedFilters = ['source_type', 'source_public_id', 'document_ids', 'updated_after', 'exclude_stale'];
        foreach (array_keys($filters) as $filter) {
            if (! in_array($filter, $allowedFilters, true)) {
                throw new InvalidArgumentException("Unsupported knowledge filter: {$filter}.");
            }
        }
    }

    public function accessScope(): KnowledgeAccessScope
    {
        return new KnowledgeAccessScope($this->companyId, $this->actorId, $this->allowedVisibilities, $this->allowedPermissions);
    }
}
