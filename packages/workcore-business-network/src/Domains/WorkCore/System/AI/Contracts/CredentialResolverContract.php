<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

interface CredentialResolverContract
{
    public function resolve(string $reference, int $companyId): ?string;
}
