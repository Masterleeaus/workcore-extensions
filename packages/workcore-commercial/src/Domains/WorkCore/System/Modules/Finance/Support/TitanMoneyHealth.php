<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Support;

use App\Domains\WorkCore\System\Modules\Finance\Application\ActionRegistry;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;

final readonly class TitanMoneyHealth
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private array $config,
        private DependencyDiagnostics $dependencies,
        private ActionRegistry $actions,
        private MoneyDomainCatalogue $domains,
    ) {
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $dependencies = $this->dependencies->report();

        return [
            'extension' => 'WorkCoreFinance',
            'version' => (string) ($this->config['version'] ?? 'unknown'),
            'schema_version' => (string) ($this->config['schema_version'] ?? 'unknown'),
            'enabled' => (bool) ($this->config['enabled'] ?? false),
            'ready' => (bool) ($this->config['enabled'] ?? false) && $dependencies['ready'],
            'security_mode' => 'fail_closed_until_platform_core_adapters_are_bound',
            'permission_count' => count(TitanMoneyPermission::cases()),
            'features' => $this->config['features'] ?? [],
            'interfaces' => $this->config['interfaces'] ?? [],
            'defaults' => $this->config['defaults'] ?? [],
            'registered_actions' => $this->actions->catalog(),
            'money_domains' => $this->domains->all(),
            'dependencies' => $dependencies,
        ];
    }
}
