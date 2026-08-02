<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Security;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\AI\Exceptions\AiPolicyException;
use App\Domains\WorkCore\System\Contracts\{OperationContextContract,PermissionResolverContract,TenantContextContract};

final class NativeAiAccessGate
{
    public function __construct(
        private TenantContextContract $tenant,
        private OperationContextContract $operation,
        private EntitlementResolverContract $entitlements,
        private PermissionResolverContract $permissions,
        private string $capability = 'workcore.ai',
        private string $usePermission = 'workcore.ai.use',
        private string $toolPermission = 'workcore.ai.execute_tools',
    ) {}

    public function assertModelAccess(int $companyId, int $actorId): void
    {
        $this->assertContext($companyId, $actorId);
        $this->assertEntitled($companyId);
        if (! $this->permissions->allows($actorId, $companyId, $this->usePermission)) {
            throw new AiPolicyException('The actor is not permitted to use WorkCore native AI.', 403);
        }
    }

    public function assertToolAccess(int $companyId, int $actorId): void
    {
        $this->assertModelAccess($companyId, $actorId);
        if (! $this->permissions->allows($actorId, $companyId, $this->toolPermission)) {
            throw new AiPolicyException('The actor is not permitted to execute WorkCore native AI tools.', 403);
        }
    }

    public function canUseTools(int $companyId, int $actorId): bool
    {
        try {
            $this->assertToolAccess($companyId, $actorId);
            return true;
        } catch (AiPolicyException) {
            return false;
        }
    }

    private function assertContext(int $companyId, int $actorId): void
    {
        if (! $this->tenant->hasTenant()
            || $this->tenant->companyId() !== $companyId
            || $this->tenant->userId() !== $actorId) {
            throw new AiPolicyException('Native AI request does not match the active WorkCore tenant.', 409);
        }

        if (! $this->operation->hasContext()
            || $this->operation->companyId() !== $companyId
            || $this->operation->actorId() !== $actorId) {
            throw new AiPolicyException('Native AI request does not match the active WorkCore operation context.', 409);
        }
    }

    private function assertEntitled(int $companyId): void
    {
        if (! $this->entitlements->allows($companyId, $this->capability)) {
            throw new AiPolicyException('The company is not entitled to WorkCore native AI.', 403);
        }
    }
}
