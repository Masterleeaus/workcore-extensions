<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\Providers;

use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class CommercialServiceProvider extends ServiceProvider
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
        $registry = $this->app->make(WorkModuleRegistry::class);
        foreach (self::MODULES as $module) {
            if ($registry->has($module)) {
                $registry->load($module);
            }
        }
    }
}
