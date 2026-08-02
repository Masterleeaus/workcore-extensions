<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Policies;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;

final class RegisteredCapabilityEntitlementResolver implements EntitlementResolverContract
{
    public function __construct(private CapabilityRegistry $capabilities) {}

    public function allows(int $companyId, ?string $capability): bool
    {
        return $capability === null || $this->capabilities->has($capability);
    }
}
