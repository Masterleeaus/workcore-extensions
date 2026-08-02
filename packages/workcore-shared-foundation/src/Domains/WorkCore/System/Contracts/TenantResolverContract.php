<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts;

use Illuminate\Http\Request;

interface TenantResolverContract
{
    /** @return array{company_id:int,user_id:?int}|null */
    public function resolve(Request $request): ?array;
}
