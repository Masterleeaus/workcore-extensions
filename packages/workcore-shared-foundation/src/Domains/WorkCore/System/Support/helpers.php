<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use App\Domains\WorkCore\System\Verticals\CompiledVerticalProfile;
use App\Domains\WorkCore\System\Verticals\TerminologyResolver;
use App\Domains\WorkCore\System\Verticals\VerticalProfileCompiler;

if (! function_exists('workcore_tenant')) {
    function workcore_tenant(): TenantContextContract
    {
        return app(TenantContextContract::class);
    }
}

if (! function_exists('workcore_company_id')) {
    function workcore_company_id(): int
    {
        return workcore_tenant()->companyId();
    }
}

if (! function_exists('workcore_vertical_profile')) {
    function workcore_vertical_profile(): CompiledVerticalProfile
    {
        try {
            $tenant = workcore_tenant();
            if ($tenant->hasTenant()) {
                return app(CompanyVerticalProfileResolver::class)->resolve($tenant->companyId());
            }
        } catch (\Throwable) {
            // Neutral terminology remains available before host tenant resolution.
        }

        return app(VerticalProfileCompiler::class)->compile(null);
    }
}

if (! function_exists('workcore_term')) {
    /** @param array<string,string|int|float> $replacements */
    function workcore_term(string $key, ?string $fallback = null, array $replacements = []): string
    {
        return app(TerminologyResolver::class)->term(
            workcore_vertical_profile(),
            $key,
            $fallback,
            $replacements,
        );
    }
}

if (! function_exists('workcore_feature_enabled')) {
    function workcore_feature_enabled(string $feature, bool $default = true): bool
    {
        return workcore_vertical_profile()->featureEnabled($feature, $default);
    }
}
