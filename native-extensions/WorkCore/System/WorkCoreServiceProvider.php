<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Extensions\WorkCore\System\Runtime\WorkCoreHostAliasRegistrar;
use App\Extensions\WorkCore\System\Runtime\WorkCoreRuntimeAutoloader;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class WorkCoreServiceProvider extends ServiceProvider implements
    ExtensionRegisterKeyProviderInterface,
    UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workcore-native.php', 'workcore-native');

        if (! (bool) config('workcore-native.enabled', true)) {
            return;
        }

        WorkCoreRuntimeAutoloader::register(__DIR__ . '/../Runtime');
        WorkCoreHostAliasRegistrar::register();

        if (! class_exists(\App\Domains\WorkCore\WorkCoreServiceProvider::class)) {
            throw new RuntimeException('The packaged WorkCore runtime is incomplete.');
        }

        $this->app->register(\App\Domains\WorkCore\WorkCoreServiceProvider::class);
    }

    public function boot(): void
    {
        if (! (bool) config('workcore-native.enabled', true)) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/workcore-native.php' => config_path('workcore-native.php'),
        ], 'extension');
    }

    public function registerKey(): string
    {
        return 'workcore';
    }

    public static function uninstall(): void
    {
        // Intentionally retain WorkCore tenant data, evidence and audit history.
        // Repeated execution is a safe no-op; destructive purge is a separate operation.
    }
}
