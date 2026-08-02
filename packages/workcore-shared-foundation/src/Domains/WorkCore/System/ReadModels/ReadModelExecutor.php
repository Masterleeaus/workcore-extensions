<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\ReadModels;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use Illuminate\Contracts\Container\Container;

final class ReadModelExecutor
{
    public function __construct(
        private readonly Container $container,
        private readonly ReadModelRegistry $registry,
        private readonly PermissionResolverContract $permissions,
        private readonly EntitlementResolverContract $entitlements,
    ) {}

    /** @param array<string|int,mixed> $filters */
    public function execute(
        string $key,
        array $filters,
        int $companyId,
        int|string $actorId,
        int $perPage = 25,
    ): mixed {
        $key = trim($key);
        if ($key === '' || $companyId < 1 || ! is_numeric($actorId) || (int) $actorId < 1) {
            throw new ReadModelExecutionException('A registered read model, active company and actor are required.', 422);
        }
        if ($this->containsTenantAuthority($filters)) {
            throw new ReadModelExecutionException('Company context cannot be supplied in read filters.', 422);
        }

        $definition = $this->registry->get($key);
        if (! $definition instanceof ReadModelDefinition) {
            throw new ReadModelExecutionException('The requested WorkCore read model is not registered.', 404);
        }
        if (! $this->entitlements->allows($companyId, $definition->capability)) {
            throw new ReadModelExecutionException('The company is not entitled to this WorkCore capability.', 403);
        }

        $permission = trim((string) ($definition->permission ?? ''));
        if ($permission !== '' && ! $this->permissions->allows($actorId, $companyId, $permission)) {
            throw new ReadModelExecutionException('The active user is not permitted to run this WorkCore read.', 403);
        }

        $handler = $this->container->make($definition->handler);
        if (! is_callable($handler)) {
            throw new ReadModelExecutionException('The registered WorkCore read handler is unavailable.', 500);
        }

        return $handler($filters, $companyId, max(1, min(100, $perPage)));
    }

    /** @param array<string|int,mixed> $input */
    private function containsTenantAuthority(array $input): bool
    {
        foreach ($input as $key => $value) {
            $normalised = strtolower(str_replace(['_', '-'], '', (string) $key));
            if ($normalised === 'companyid') {
                return true;
            }
            if (is_array($value) && $this->containsTenantAuthority($value)) {
                return true;
            }
        }

        return false;
    }
}
