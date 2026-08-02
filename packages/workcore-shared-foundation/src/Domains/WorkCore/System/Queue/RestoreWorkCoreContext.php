<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Queue;

use App\Domains\WorkCore\System\Context\OperationContextSnapshot;
use App\Domains\WorkCore\System\Context\TenantContextSnapshot;
use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use RuntimeException;

final class RestoreWorkCoreContext
{
    public function handle(object $job, callable $next): mixed
    {
        if (! $job instanceof CarriesWorkCoreContext) {
            throw new RuntimeException('Queued WorkCore job must carry an execution context.');
        }

        $payload = $job->workCoreContextPayload();
        if (! isset($payload['tenant'], $payload['operation'])) {
            throw new RuntimeException('Queued WorkCore job contains an invalid execution context payload.');
        }

        $tenant = app(TenantContextContract::class);
        $operation = app(OperationContextContract::class);
        $previousTenant = $tenant->hasTenant() ? $tenant->snapshot() : null;
        $previousOperation = $operation->hasContext() ? $operation->snapshot() : null;
        $previousLocale = app()->getLocale();
        $previousTimezone = date_default_timezone_get();

        $tenant->restore(TenantContextSnapshot::fromArray($payload['tenant']));
        $operation->restore(OperationContextSnapshot::fromArray($payload['operation']));
        app()->setLocale($operation->locale());
        date_default_timezone_set($operation->timezone());

        try {
            return $next($job);
        } finally {
            if ($previousOperation !== null) {
                $operation->restore($previousOperation);
            } else {
                $operation->clear();
            }

            if ($previousTenant !== null) {
                $tenant->restore($previousTenant);
            } else {
                $tenant->clear();
            }

            app()->setLocale($previousLocale);
            date_default_timezone_set($previousTimezone);
        }
    }
}
