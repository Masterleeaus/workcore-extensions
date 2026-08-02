<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use App\Domains\WorkCore\System\Host\Contracts\ToolBridgeContract;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;

final class WorkCoreToolBridge implements ToolBridgeContract
{
    public function __construct(
        private BusinessActionRegistry $actions,
        private ReadModelRegistry $reads,
        private TenantContextContract $tenant,
        private EntitlementResolverContract $entitlements,
        private PermissionResolverContract $permissions,
    ) {}

    public function tools(): array
    {
        $tools = [];
        $companyId = $this->tenant->hasTenant() ? $this->tenant->companyId() : null;
        $actorId = $this->tenant->hasTenant() ? $this->tenant->userId() : null;

        foreach ($this->actions->all() as $key => $definition) {
            if (! $this->visible($companyId, $actorId, $definition->capability, $definition->permission)) {
                continue;
            }
            $tools[$key] = [
                'kind' => 'action',
                'risk' => $definition->risk,
                'confirmation_required' => $definition->requiresConfirmation,
                'capability' => $definition->capability,
                'permission' => $definition->permission,
            ];
        }

        foreach ($this->reads->all() as $key => $definition) {
            if (! $this->visible($companyId, $actorId, $definition->capability, $definition->permission)) {
                continue;
            }
            $tools[$key] = [
                'kind' => 'read_model',
                'capability' => $definition->capability,
                'permission' => $definition->permission,
            ];
        }

        ksort($tools);
        return $tools;
    }

    private function visible(?int $companyId, ?int $actorId, ?string $capability, ?string $permission): bool
    {
        if ($companyId === null || $actorId === null) {
            return false;
        }
        if (! $this->entitlements->allows($companyId, $capability)) {
            return false;
        }
        return $permission === null || $this->permissions->allows($actorId, $companyId, $permission);
    }
}
