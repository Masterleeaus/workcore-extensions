<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host\Contracts;

interface StorageAdapterContract
{
    public function put(string $path, string $contents, array $options = []): bool;
    public function temporaryUrl(string $path, int $minutes = 15): ?string;
    public function delete(string $path): bool;
}
