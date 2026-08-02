<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

interface KnowledgeIndexStatusContract
{
    /** @return array<string,mixed>|null */
    public function status(int $companyId, string $documentId): ?array;

    public function purgeExpired(int $companyId, ?string $before = null): int;
}
