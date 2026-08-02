<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\MemoryStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\MemoryItem;
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Security\{NativeAiAccessGate,SensitiveDataRedactor};
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;

final class MemoryService
{
    public function __construct(
        private NativeAiAccessGate $access,
        private PermissionResolverContract $permissions,
        private MemoryStoreContract $store,
        private SensitiveDataRedactor $redactor,
        private string $permission = 'workcore.ai.manage_memory',
    ) {}

    /** @return list<MemoryItem> */
    public function list(int $companyId, int $actorId, int $limit = 100): array
    {
        $this->authorise($companyId, $actorId);
        return $this->store->listForActor($companyId, $actorId, $limit);
    }

    public function remember(int $companyId, int $actorId, ?string $agentPublicId, string $scope, string $key, string $value, float $confidence = 1.0, ?string $expiresAt = null): MemoryItem
    {
        $this->authorise($companyId, $actorId);
        if ($scope === 'company' && ! $this->permissions->allows($actorId, $companyId, 'workcore.ai.manage_company_memory')) {
            throw new AiOrchestrationException('The actor is not permitted to manage company-wide AI memory.', 403);
        }
        $candidate = ['key' => trim($key), 'value' => trim($value)];
        if ($this->redactor->redact($candidate) !== $candidate || $this->looksLikeSecret($candidate['value'])) {
            throw new AiOrchestrationException('Secrets and sensitive credentials cannot be stored in WorkCore AI memory.', 422);
        }
        return $this->store->remember($companyId, $actorId, $agentPublicId, $scope, $candidate['key'], $candidate['value'], $confidence, 'explicit_user', $expiresAt);
    }

    public function forget(int $companyId, int $actorId, string $memoryPublicId): bool
    {
        $this->authorise($companyId, $actorId);
        return $this->store->forget($companyId, $actorId, $memoryPublicId);
    }

    private function looksLikeSecret(string $value): bool
    {
        return preg_match('/(?:-----BEGIN [A-Z ]+PRIVATE KEY-----|\bBearer\s+[A-Za-z0-9._~+\/-]+=*|\bsk-[A-Za-z0-9_-]{16,}|\b(?:api[_-]?key|client[_-]?secret|password)\s*[:=])/i', $value) === 1;
    }

    private function authorise(int $companyId, int $actorId): void
    {
        $this->access->assertModelAccess($companyId, $actorId);
        if (! $this->permissions->allows($actorId, $companyId, $this->permission)) {
            throw new AiOrchestrationException('The actor is not permitted to manage WorkCore AI memory.', 403);
        }
    }
}
