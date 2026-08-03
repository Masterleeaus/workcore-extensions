<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

interface EntitlementRevisionResolverContract
{
    public function revision(int $companyId): int;
}
