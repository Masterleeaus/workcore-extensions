<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use App\Domains\WorkCore\System\Host\Contracts\StorageAdapterContract;
use Illuminate\Contracts\Filesystem\Factory;
use Throwable;

final class LaravelPrivateStorageAdapter implements StorageAdapterContract
{
    public function __construct(private Factory $filesystems) {}

    public function put(string $path, string $contents, array $options = []): bool
    {
        $options['visibility'] = 'private';

        return $this->disk()->put($this->normalise($path), $contents, $options);
    }

    public function temporaryUrl(string $path, int $minutes = 15): ?string
    {
        try {
            return $this->disk()->temporaryUrl($this->normalise($path), now()->addMinutes(max(1, $minutes)));
        } catch (Throwable) {
            return null;
        }
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($this->normalise($path));
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->filesystems->disk((string) config('workcore.storage.disk', 'local'));
    }

    private function normalise(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '../') || str_contains($path, '/..')) {
            throw new \InvalidArgumentException('Invalid WorkCore storage path.');
        }

        return 'workcore/' . $path;
    }
}
