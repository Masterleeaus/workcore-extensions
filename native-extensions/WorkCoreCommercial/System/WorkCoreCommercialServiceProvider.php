<?php

declare(strict_types=1);

namespace App\Extensions\WorkCoreCommercial\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use App\Domains\WorkCore\WorkCoreServiceProvider;
use Illuminate\Support\ServiceProvider;

final class WorkCoreCommercialServiceProvider extends ServiceProvider implements
    ExtensionRegisterKeyProviderInterface,
    UninstallExtensionServiceProviderInterface
{
    private const MODULES = [
        'finance',
        'payroll',
        'inventory',
        'supply',
        'vault',
        'trust_accounting',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workcore-commercial.php', 'workcore-commercial');

        if (! (bool) config('workcore-commercial.enabled', true)) {
            return;
        }

        if (! class_exists(WorkCoreServiceProvider::class)
            || ! $this->app->bound(WorkModuleRegistry::class)) {
            return;
        }

        // Direct Titan Money routes currently carry standalone Sanctum middleware.
        // Keep them disabled inside MagicAI; finance remains available through the
        // governed WorkCore action/read-model APIs protected by native Passport.
        $this->app['config']->set('workcore.finance.routes_enabled', false);

        /** @var WorkModuleRegistry $registry */
        $registry = $this->app->make(WorkModuleRegistry::class);
        $registry->loadMany(self::MODULES);
    }

    public function boot(): void
    {
        if (! (bool) config('workcore-commercial.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/workcore-commercial.php' => config_path('workcore-commercial.php'),
        ], 'extension');
    }

    public function registerKey(): string
    {
        return 'workcore-commercial';
    }

    public static function uninstall(): void
    {
        // Add-ons own activation only. Parent-owned WorkCore data is retained.
    }
}
