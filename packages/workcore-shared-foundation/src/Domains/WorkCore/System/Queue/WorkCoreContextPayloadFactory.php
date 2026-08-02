<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Queue;

use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use RuntimeException;

final class WorkCoreContextPayloadFactory
{
    /** @return array{tenant:array<string,mixed>,operation:array<string,mixed>} */
    public function capture(): array
    {
        $tenant = app(TenantContextContract::class);
        $operation = app(OperationContextContract::class);

        if (! $tenant->hasTenant() || ! $operation->hasContext()) {
            throw new RuntimeException('A resolved WorkCore context is required before dispatching this job.');
        }

        return [
            'tenant' => $tenant->snapshot()->toArray(),
            'operation' => $operation->snapshot()->toArray(),
        ];
    }
}
