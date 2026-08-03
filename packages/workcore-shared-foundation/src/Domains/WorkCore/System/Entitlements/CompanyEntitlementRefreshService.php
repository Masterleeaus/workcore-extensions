<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Entitlements;

use App\Domains\WorkCore\System\Entitlements\Contracts\EffectiveSubscriptionResolverContract;

final class CompanyEntitlementRefreshService
{
    public function __construct(
        private EffectiveSubscriptionResolverContract $subscriptions,
        private WorkCorePlanEntitlementAdapter $adapter,
    ) {}

    public function refresh(int $companyId): int
    {
        $subscription = $this->subscriptions->resolve($companyId);

        return $this->adapter->project($companyId, $subscription);
    }
}
