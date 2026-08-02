<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class MagicAIExtensionFixtureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $providers = array_values(array_filter(array_map(
            static fn (string $provider): string => trim($provider),
            explode(',', (string) env('MAGICAI_EXTENSION_PROVIDERS', '')),
        )));

        foreach ($providers as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }
}
