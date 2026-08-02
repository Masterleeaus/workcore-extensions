<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

interface EntitlementResolverContract
{
    public function allows(int $companyId, ?string $capability): bool;
}
