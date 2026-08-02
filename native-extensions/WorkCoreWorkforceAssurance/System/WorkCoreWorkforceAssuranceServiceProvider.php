<?php

declare(strict_types=1);

namespace App\Extensions\WorkCoreWorkforceAssurance\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use App\Domains\WorkCore\WorkCoreServiceProvider;
use Illuminate\Support\ServiceProvider;

final class WorkCoreWorkforceAssuranceServiceProvider extends ServiceProvider implements
    ExtensionRegisterKeyProviderInterface,
    UninstallExtensionServiceProviderInterface
{
    private const MODULES = [
        'workforce',
        'people',
        'attendance_verification',
        'rosters',
        'attendance',
        'compliance',
        'assurance',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workcore-workforce-assurance.php', 'workcore-workforce-assurance');

        if (! (bool) config('workcore-workforce-assurance.enabled', true)) {
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
        if (! (bool) config('workcore-workforce-assurance.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/workcore-workforce-assurance.php' => config_path('workcore-workforce-assurance.php'),
        ], 'extension');
    }

    public function registerKey(): string
    {
        return 'workcore-workforce-assurance';
    }

    public static function uninstall(): void
    {
        // Add-ons own activation only. Parent-owned WorkCore data is retained.
    }
}
