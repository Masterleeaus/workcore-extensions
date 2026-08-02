<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Runtime;

use RuntimeException;

final class WorkCoreHostAliasRegistrar
{
    /** @var array<class-string,class-string> */
    private const ALIASES = [
        'App\Console\Commands\WorkCoreBootstrapCompanyCommand'
            => 'App\Extensions\WorkCore\System\Console\Commands\WorkCoreBootstrapCompanyCommand',
        'App\Http\Controllers\Api\V1\WorkCore\ActionController'
            => 'App\Extensions\WorkCore\System\Http\Controllers\ActionController',
        'App\Http\Controllers\Api\V1\WorkCore\BusinessFlowController'
            => 'App\Extensions\WorkCore\System\Http\Controllers\BusinessFlowController',
        'App\Services\WorkCore\CustomerPropertyWorkOrderFlow'
            => 'App\Extensions\WorkCore\System\Services\CustomerPropertyWorkOrderFlow',
        'App\Support\WorkCore\WorkCoreTenantResolver'
            => 'App\Extensions\WorkCore\System\Resolvers\WorkCoreTenantResolver',
        'App\Support\WorkCore\WorkCorePermissionResolver'
            => 'App\Extensions\WorkCore\System\Resolvers\WorkCorePermissionResolver',
    ];

    public static function register(): void
    {
        foreach (self::ALIASES as $legacy => $replacement) {
            if (class_exists($legacy, false) || interface_exists($legacy, false)) {
                continue;
            }

            if (! class_exists($replacement)) {
                throw new RuntimeException("Missing WorkCore host adapter [{$replacement}].");
            }

            if (! class_alias($replacement, $legacy)) {
                throw new RuntimeException("Unable to register WorkCore host alias [{$legacy}].");
            }
        }
    }
}
