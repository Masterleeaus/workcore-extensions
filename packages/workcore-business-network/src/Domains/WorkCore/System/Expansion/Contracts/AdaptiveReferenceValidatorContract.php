<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Contracts;

interface AdaptiveReferenceValidatorContract
{
    public function assertAccessible(int $companyId, string $referenceType, string $identifier, string $field): void;
}
