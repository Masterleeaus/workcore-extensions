<?php

declare(strict_types=1);

namespace App\Extensions\WorkCorePropertyOperations\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use App\Domains\WorkCore\WorkCoreServiceProvider;
use Illuminate\Support\ServiceProvider;

final class WorkCorePropertyOperationsServiceProvider extends ServiceProvider implements
    ExtensionRegisterKeyProviderInterface,
    UninstallExtensionServiceProviderInterface
{
    private const MODULES = [
        'premises',
        'assets',
        'documents',
        'vertical_operations',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workcore-property-operations.php', 'workcore-property-operations');

        if (! (bool) config('workcore-property-operations.enabled', true)) {
            return;
        }

        if (! class_exists(WorkCoreServiceProvider::class)
            || ! $this->app->bound(WorkModuleRegistry::class)) {
            return;
        }

        /** @var WorkModuleRegistry $registry */
        $registry = $this->app->make(WorkModuleRegistry::class);
        $registry->loadMany(self::MODULES);
    }

    public function boot(): void
    {
        if (! (bool) config('workcore-property-operations.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/workcore-property-operations.php' => config_path('workcore-property-operations.php'),
        ], 'extension');
    }

    public function registerKey(): string
    {
        return 'workcore-property-operations';
    }

    public static function uninstall(): void
    {
        // Add-ons own activation only. Parent-owned WorkCore data is retained.
    }
}
