<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Context\CompanyOperatingContextResolver;
use App\Domains\WorkCore\System\Context\ContextIdNormalizer;
use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Contracts\TenantResolverContract;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveWorkCoreTenant
{
    public function __construct(
        private TenantResolverContract $resolver,
        private TenantContextContract $tenant,
        private OperationContextContract $operation,
        private CompanyOperatingContextResolver $operatingContext,
        private ContextIdNormalizer $ids,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->resolver->resolve($request);
        if ($identity === null) {
            abort_if((bool) config('workcore.require_tenant', true), 409, 'No active Titan company context.');

            return $next($request);
        }

        $companyId = (int) $identity['company_id'];
        $userId = isset($identity['user_id']) ? (int) $identity['user_id'] : null;
        $companyContext = $this->operatingContext->resolve(
            $companyId,
            method_exists($request, 'getLocale') ? $request->getLocale() : null,
        );
        $correlationId = $this->ids->internal($request->header('X-Correlation-ID'));
        $causationId = $this->ids->optional($request->header('X-Causation-ID'));

        $previousTenant = $this->tenant->hasTenant() ? $this->tenant->snapshot() : null;
        $previousOperation = $this->operation->hasContext() ? $this->operation->snapshot() : null;
        $previousLocale = app()->getLocale();
        $previousTimezone = date_default_timezone_get();

        $this->tenant->set($companyId, $userId);
        $this->operation->set(
            companyId: $companyId,
            actorId: $userId,
            correlationId: $correlationId,
            causationId: $causationId,
            locale: $companyContext->locale,
            timezone: $companyContext->timezone,
            actorSubject: (string) ($identity['actor_subject'] ?? ($userId === null ? 'system' : 'magicai:user:' . $userId)),
            workerId: isset($identity['worker_id']) ? (int) $identity['worker_id'] : null,
            branchId: isset($identity['branch_id']) ? (int) $identity['branch_id'] : null,
            territoryId: isset($identity['territory_id']) ? (int) $identity['territory_id'] : null,
            deviceId: isset($identity['device_id']) ? (string) $identity['device_id'] : null,
            authenticationAssurance: (string) ($identity['authentication_assurance'] ?? 'authenticated'),
            securityRevision: (int) ($identity['security_revision'] ?? 0),
            membershipRevision: (int) ($identity['membership_revision'] ?? 0),
        );
        app()->setLocale($companyContext->locale);
        date_default_timezone_set($companyContext->timezone);

        try {
            return $next($request);
        } finally {
            if ($previousOperation !== null) {
                $this->operation->restore($previousOperation);
            } else {
                $this->operation->clear();
            }

            if ($previousTenant !== null) {
                $this->tenant->restore($previousTenant);
            } else {
                $this->tenant->clear();
            }

            app()->setLocale($previousLocale);
            date_default_timezone_set($previousTimezone);
        }
    }
}
