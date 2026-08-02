<?php

namespace App\Providers;

use App\Console\Commands\WorkCoreSchemaPreflightCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([WorkCoreSchemaPreflightCommand::class]);
        }
    }
}
