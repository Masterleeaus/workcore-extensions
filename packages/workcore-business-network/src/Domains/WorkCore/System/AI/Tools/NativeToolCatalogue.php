<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\AI\Contracts\ToolCatalogueContract;
use App\Domains\WorkCore\System\AI\Security\NativeAiAccessGate;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Contracts\Container\Container;
use LogicException;

final class NativeToolCatalogue implements ToolCatalogueContract
{
    private bool $loaded = false;

    /** @param list<class-string<DomainToolRegistryContract>> $domainRegistries */
    public function __construct(
        private Container $container,
        private BusinessActionRegistry $actions,
        private ReadModelRegistry $reads,
        private EntitlementResolverContract $entitlements,
        private PermissionResolverContract $permissions,
        private NativeAiAccessGate $access,
        private AiToolRegistry $registry,
        private array $domainRegistries,
    ) {}

    public function get(string $name): ?AiToolDefinition
    {
        $this->load();
        return $this->registry->get($name);
    }

    /** @return array<string,AiToolDefinition> */
    public function all(): array
    {
        $this->load();
        return $this->registry->all();
    }

    /** @return array<string,AiToolDefinition> */
    public function available(int $companyId, int $actorId, string $channel = 'internal', string $agent = '*'): array
    {
        if (! $this->access->canUseTools($companyId, $actorId)) {
            return [];
        }

        $available = [];
        foreach ($this->all() as $name => $tool) {
            if (! $tool->enabled || ! $this->matches($channel, $tool->channels) || ! $this->matches($agent, $tool->agents)) {
                continue;
            }
            if (! $this->entitlements->allows($companyId, $tool->capability)) {
                continue;
            }
            if ($tool->permission !== null && ! $this->permissions->allows($actorId, $companyId, $tool->permission)) {
                continue;
            }
            $available[$name] = $tool;
        }
        ksort($available);
        return $available;
    }

    /** @return list<array<string,mixed>> */
    public function modelTools(int $companyId, int $actorId, string $channel = 'internal', string $agent = '*'): array
    {
        return array_values(array_map(static fn (AiToolDefinition $tool): array => $tool->toModelTool(), $this->available($companyId, $actorId, $channel, $agent)));
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        foreach ($this->domainRegistries as $class) {
            $domain = $this->container->make($class);
            if (! $domain instanceof DomainToolRegistryContract) {
                throw new LogicException("Native AI registry [{$class}] must implement DomainToolRegistryContract.");
            }
            foreach ($domain->registerTools() as $tool) {
                if (! $tool instanceof AiToolDefinition) {
                    throw new LogicException("Native AI registry [{$class}] returned an invalid tool definition.");
                }
                $this->registry->register($this->authoritative($tool));
            }
        }
        $this->loaded = true;
    }

    private function authoritative(AiToolDefinition $tool): AiToolDefinition
    {
        if ($tool->kind === 'action') {
            $definition = $this->actions->get($tool->target)
                ?? throw new LogicException("AI tool [{$tool->name}] targets unregistered action [{$tool->target}].");
            return $tool->withGovernance($definition->capability, $definition->permission, $definition->risk, $definition->requiresConfirmation);
        }

        $definition = $this->reads->get($tool->target)
            ?? throw new LogicException("AI tool [{$tool->name}] targets unregistered read model [{$tool->target}].");
        return $tool->withGovernance($definition->capability, $definition->permission, 'low', false);
    }

    /** @param list<string> $patterns */
    private function matches(string $value, array $patterns): bool
    {
        return in_array('*', $patterns, true) || in_array($value, $patterns, true);
    }
}
