<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\ApprovalStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\ApprovalRecord;
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use App\Domains\WorkCore\System\AI\Security\NativeAiAccessGate;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;

final class ApprovalService
{
    public function __construct(
        private NativeAiAccessGate $access,
        private PermissionResolverContract $permissions,
        private ApprovalStoreContract $store,
        private string $permission = 'workcore.ai.approve_actions',
    ) {}

    public function decide(int $companyId, int $actorId, string $approvalPublicId, string $decision, ?string $note = null): ApprovalRecord
    {
        $this->access->assertToolAccess($companyId, $actorId);
        if (! $this->permissions->allows($actorId, $companyId, $this->permission)) {
            throw new AiOrchestrationException('The actor is not permitted to approve WorkCore AI actions.', 403);
        }
        return $this->store->decide($companyId, $approvalPublicId, $actorId, $decision, $note);
    }
}
