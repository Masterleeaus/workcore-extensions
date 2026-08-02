<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System;

use Illuminate\Support\ServiceProvider;

final class WorkCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! (bool) config('workcore.enabled', true)) {
            return;
        }

        $provider = \App\Domains\WorkCore\WorkCoreServiceProvider::class;
        if (! class_exists($provider)) {
            throw new \RuntimeException(
                'WorkCore domain packages are not installed or autoloadable. Install the shared foundation and selected domain packages before enabling the MagicAI WorkCore extension.'
            );
        }

        $this->app->register($provider);
    }
}
