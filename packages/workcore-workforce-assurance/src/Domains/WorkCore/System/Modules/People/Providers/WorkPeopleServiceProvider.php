<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Providers;

use Illuminate\Support\ServiceProvider;

final class WorkPeopleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('workcore.modules.people.status', [
            'mode' => 'domain_schema_foundation',
            'runtime_enabled' => false,
            'description' => 'Organisation, employment, recruitment, onboarding, compliance and performance schemas are present; runtime services remain opt-in.',
        ]);
    }

    public function boot(): void {}
}
