<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Payroll\Providers;

use Illuminate\Support\ServiceProvider;

final class WorkPayrollServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('workcore.modules.payroll.status', [
            'mode' => 'domain_schema_foundation',
            'runtime_enabled' => false,
            'description' => 'Payroll calculation and persistence foundations are present; application runtime remains opt-in.',
        ]);
    }

    public function boot(): void {}
}
