<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Policies;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use Illuminate\Contracts\Container\Container;
use LogicException;

final class VerticalAwareEntitlementResolver implements EntitlementResolverContract
{
    public function __construct(
        private Container $container,
        private CompanyVerticalProfileResolver $profiles,
    ) {}

    public function allows(int $companyId, ?string $capability): bool
    {
        $resolverClass = (string) config(
            'workcore.host.entitlements.plan_resolver',
            MagicAIPlanEntitlementResolver::class,
        );

        if ($resolverClass === self::class) {
            throw new LogicException('The WorkCore plan entitlement resolver cannot reference VerticalAwareEntitlementResolver.');
        }

        $planResolver = $this->container->make($resolverClass);
        if (! $planResolver instanceof EntitlementResolverContract) {
            throw new LogicException("Plan entitlement resolver [{$resolverClass}] must implement EntitlementResolverContract.");
        }

        if (! $planResolver->allows($companyId, $capability)) {
            return false;
        }

        return $capability === null || $this->profiles->resolve($companyId)->supportsCapability($capability);
    }
}
