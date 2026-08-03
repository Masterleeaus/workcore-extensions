<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Http\Middleware;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireWorkspaceCapability
{
    public function __construct(
        private TenantContextContract $tenant,
        private CapabilityRegistry $registry,
        private EntitlementResolverContract $entitlements,
    ) {}

    public function handle(Request $request, Closure $next, string $capabilities): Response
    {
        if (! $this->tenant->hasTenant()) {
            throw new AuthorizationException('No active Titan company context.');
        }

        $required = array_values(array_unique(array_filter(
            array_map('trim', explode('|', $capabilities)),
            static fn (string $capability): bool => str_starts_with($capability, 'workcore.'),
        )));
        if ($required === []) {
            throw new AuthorizationException('A WorkCore workspace capability is required.');
        }

        $companyId = $this->tenant->companyId();
        foreach ($required as $capability) {
            if ($this->registry->has($capability)
                && $this->entitlements->allows($companyId, $capability)) {
                return $next($request);
            }
        }

        throw new AuthorizationException('The active company is not entitled to this WorkCore workspace.');
    }
}
