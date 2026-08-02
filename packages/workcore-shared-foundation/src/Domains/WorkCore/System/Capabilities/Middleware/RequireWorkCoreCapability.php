<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Capabilities\Middleware;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed capability gate for WorkCore HTTP surfaces.
 */
final class RequireWorkCoreCapability
{
    public function __construct(
        private TenantContextContract $tenant,
        private EntitlementResolverContract $entitlements,
    ) {
    }

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $capability = trim($capability);
        if ($capability === '') {
            throw new AuthorizationException('A WorkCore capability is required.');
        }

        if (! $this->tenant->hasTenant()) {
            throw new AuthorizationException('No active Titan company context.');
        }

        if (! $this->entitlements->allows($this->tenant->companyId(), $capability)) {
            throw new AuthorizationException("The active company is not entitled to [{$capability}].");
        }

        return $next($request);
    }
}
