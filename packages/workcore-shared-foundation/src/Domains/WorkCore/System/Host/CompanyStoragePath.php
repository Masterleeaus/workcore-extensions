<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use InvalidArgumentException;

final class CompanyStoragePath
{
    public function __construct(private string $prefix = 'workcore') {}

    public function make(int $companyId, string $path): string
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('A valid company ID is required.');
        }
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '' || preg_match('#(^|/)\.\.(/|$)#', $path) === 1 || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Invalid company storage path.');
        }
        $prefix = trim($this->prefix, '/');
        if ($prefix === '') {
            throw new InvalidArgumentException('A storage prefix is required.');
        }
        return "{$prefix}/companies/{$companyId}/{$path}";
    }
}
