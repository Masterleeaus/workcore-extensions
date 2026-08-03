<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Entitlements\Contracts;

use App\Domains\WorkCore\System\Entitlements\EffectiveSubscriptionSnapshot;

interface EffectiveSubscriptionResolverContract
{
    public function resolve(int $companyId): EffectiveSubscriptionSnapshot;
}
