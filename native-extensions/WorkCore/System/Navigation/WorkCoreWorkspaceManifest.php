<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Navigation;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementRevisionResolverContract;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Auth\Access\AuthorizationException;

final class WorkCoreWorkspaceManifest
{
    public function __construct(
        private WorkCoreWorkspaceCatalogue $catalogue,
        private CapabilityRegistry $capabilities,
        private EntitlementResolverContract $entitlements,
        private EntitlementRevisionResolverContract $revisions,
        private TenantContextContract $tenant,
    ) {}

    /** @return array<string,mixed> */
    public function forActiveCompany(): array
    {
        if (! $this->tenant->hasTenant()) {
            throw new AuthorizationException('No active Titan company context.');
        }

        return $this->forCompany($this->tenant->companyId());
    }

    /** @return array<string,mixed> */
    public function forCompany(int $companyId): array
    {
        if ($companyId < 1) {
            throw new AuthorizationException('A valid Titan company is required.');
        }

        $workspaces = [];
        foreach ($this->catalogue->all() as $workspaceKey => $workspace) {
            $sections = [];
            foreach ($workspace['sections'] as $sectionKey => $section) {
                if (! $this->allowsAny($companyId, $section['capabilities'])) {
                    continue;
                }
                $sections[] = $this->present($sectionKey, $section);
            }
            if ($sections === []) {
                continue;
            }

            $presented = $this->present($workspaceKey, $workspace);
            $presented['capabilities'] = array_values(array_unique(array_merge(
                ...array_map(
                    static fn (array $section): array => $section['capabilities'],
                    $sections,
                ),
            )));
            $presented['sections'] = $sections;
            $workspaces[] = $presented;
        }

        return [
            'company_id' => $companyId,
            'entitlement_revision' => $this->revisions->revision($companyId),
            'workspaces' => $workspaces,
        ];
    }

    /** @return array<string,mixed>|null */
    public function findForCompany(int $companyId, string $workspace): ?array
    {
        foreach ($this->forCompany($companyId)['workspaces'] as $definition) {
            if (($definition['key'] ?? null) === trim($workspace)) {
                return $definition;
            }
        }

        return null;
    }

    /** @param list<string> $required */
    private function allowsAny(int $companyId, array $required): bool
    {
        foreach ($required as $capability) {
            if ($this->capabilities->has($capability)
                && $this->entitlements->allows($companyId, $capability)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function present(string $key, array $definition): array
    {
        return [
            'key' => $key,
            'menu_key' => $definition['menu_key'],
            'label' => $definition['label'],
            'icon' => $definition['icon'],
            'route_name' => $definition['route_name'],
            'path' => '/dashboard/user/workcore/' . $definition['path'],
            'order' => $definition['order'],
            'description' => $definition['description'],
            'capabilities' => $definition['capabilities'],
        ];
    }
}
