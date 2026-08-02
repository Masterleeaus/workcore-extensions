<?php

declare(strict_types=1);

namespace App\Extensions\WorkCoreBusinessNetwork\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use App\Domains\WorkCore\WorkCoreServiceProvider;
use Illuminate\Support\ServiceProvider;

final class WorkCoreBusinessNetworkServiceProvider extends ServiceProvider implements
    ExtensionRegisterKeyProviderInterface,
    UninstallExtensionServiceProviderInterface
{
    private const MODULES = [
        'crm',
        'catalogue',
        'support',
        'knowledge',
        'reviews',
        'territories',
        'intelligence',
        'expansion',
        'wizards',
        'ai',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workcore-business-network.php', 'workcore-business-network');

        if (! (bool) config('workcore-business-network.enabled', true)) {
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
        if (! (bool) config('workcore-business-network.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/workcore-business-network.php' => config_path('workcore-business-network.php'),
        ], 'extension');
    }

    public function registerKey(): string
    {
        return 'workcore-business-network';
    }

    public static function uninstall(): void
    {
        // Add-ons own activation only. Parent-owned WorkCore data is retained.
    }
}
