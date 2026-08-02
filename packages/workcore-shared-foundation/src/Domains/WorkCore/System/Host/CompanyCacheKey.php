<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use InvalidArgumentException;

final class CompanyCacheKey
{
    public function __construct(private string $prefix = 'workcore') {}

    public function make(int $companyId, string $key): string
    {
        if ($companyId < 1 || trim($key) === '') {
            throw new InvalidArgumentException('A valid company and cache key are required.');
        }
        $normalised = preg_replace('/[^A-Za-z0-9:_-]+/', '_', trim($key));
        if (! is_string($normalised) || $normalised === '') {
            throw new InvalidArgumentException('A valid cache key is required.');
        }
        return trim($this->prefix, ':').':company:'.$companyId.':'.$normalised;
    }
}
