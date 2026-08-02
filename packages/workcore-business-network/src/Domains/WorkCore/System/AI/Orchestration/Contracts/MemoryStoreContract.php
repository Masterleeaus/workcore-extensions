<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Contracts;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\MemoryItem;

interface MemoryStoreContract
{
    /** @return list<MemoryItem> */
    public function recall(int $companyId, int $actorId, ?string $agentPublicId, int $limit): array;

    public function remember(
        int $companyId,
        int $actorId,
        ?string $agentPublicId,
        string $scope,
        string $key,
        string $value,
        float $confidence,
        string $source,
        ?string $expiresAt = null,
    ): MemoryItem;

    public function forget(int $companyId, int $actorId, string $memoryPublicId): bool;

    /** @return list<MemoryItem> */
    public function listForActor(int $companyId, int $actorId, int $limit = 100): array;
}
