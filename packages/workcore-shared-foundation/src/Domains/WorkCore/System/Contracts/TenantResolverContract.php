<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts;

use Illuminate\Http\Request;

interface TenantResolverContract
{
    /**
     * @return array{
     *   company_id:int,
     *   user_id:?int,
     *   actor_subject?:string,
     *   worker_id?:?int,
     *   branch_id?:?int,
     *   territory_id?:?int,
     *   device_id?:?string,
     *   authentication_assurance?:string,
     *   security_revision?:int,
     *   membership_revision?:int
     * }|null
     */
    public function resolve(Request $request): ?array;
}
