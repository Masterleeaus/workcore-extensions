<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Security;

use App\Domains\WorkCore\System\AI\Contracts\CredentialResolverContract;

/** Fail-closed default. The host must bind a company-scoped BYO-key or vault resolver. */
final class NullCredentialResolver implements CredentialResolverContract
{
    public function resolve(string $reference, int $companyId): ?string
    {
        return null;
    }
}
