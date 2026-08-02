<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Contracts\TenantResolverContract;
use Illuminate\Http\Request;

final class NullTenantResolver implements TenantResolverContract
{
    public function resolve(Request $request): ?array { return null; }
}
