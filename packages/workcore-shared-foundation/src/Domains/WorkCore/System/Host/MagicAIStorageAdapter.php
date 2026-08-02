<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use App\Domains\WorkCore\System\Host\Contracts\StorageAdapterContract;
use Illuminate\Support\Facades\Storage;

final class MagicAIStorageAdapter implements StorageAdapterContract
{
    public function put(string $path, string $contents, array $options = []): bool
    {
        return Storage::disk((string) config('workcore.host.storage.disk', config('filesystems.default')))
            ->put($this->prefix($path), $contents, $options);
    }

    public function temporaryUrl(string $path, int $minutes = 15): ?string
    {
        $disk = Storage::disk((string) config('workcore.host.storage.disk', config('filesystems.default')));
        if (! method_exists($disk, 'temporaryUrl')) {
            return null;
        }
        return $disk->temporaryUrl($this->prefix($path), now()->addMinutes($minutes));
    }

    public function delete(string $path): bool
    {
        return Storage::disk((string) config('workcore.host.storage.disk', config('filesystems.default')))
            ->delete($this->prefix($path));
    }

    private function prefix(string $path): string
    {
        return trim((string) config('workcore.host.storage.prefix', 'workcore'), '/') . '/' . ltrim($path, '/');
    }
}
