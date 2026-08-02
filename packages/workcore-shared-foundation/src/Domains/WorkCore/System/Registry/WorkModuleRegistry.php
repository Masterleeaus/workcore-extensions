<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Registry;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use LogicException;

final class WorkModuleRegistry
{
    /** @var array<string,class-string> */
    private array $providers = [];

    /** @var array<string,true> */
    private array $loaded = [];

    public function __construct(private Application $app) {}

    /**
     * Backward-compatible registration. New foundation code should define all
     * modules first and let an aggregate provider load the relevant group.
     *
     * @param class-string $provider
     */
    public function register(string $key, string $provider, bool $load = true): void
    {
        $this->define($key, $provider);

        if ($load) {
            $this->load($key);
        }
    }

    /** @param class-string $provider */
    public function define(string $key, string $provider): void
    {
        if ($key === '' || ! class_exists($provider)) {
            throw new InvalidArgumentException("Invalid WorkCore module [{$key}].");
        }

        if (isset($this->providers[$key])) {
            throw new InvalidArgumentException("WorkCore module [{$key}] is already defined.");
        }

        $this->providers[$key] = $provider;
    }

    public function load(string $key): void
    {
        if (! isset($this->providers[$key])) {
            throw new LogicException("WorkCore module [{$key}] has not been defined.");
        }

        if (isset($this->loaded[$key])) {
            return;
        }

        $this->app->register($this->providers[$key]);
        $this->loaded[$key] = true;
    }

    /** @param list<string> $keys */
    public function loadMany(array $keys): void
    {
        foreach ($keys as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $this->load($key);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /** @return array<string,class-string> */
    public function all(): array
    {
        return $this->providers;
    }

    /** @return array<string,class-string> */
    public function loaded(): array
    {
        return array_intersect_key($this->providers, $this->loaded);
    }

    public function isLoaded(string $key): bool
    {
        return isset($this->loaded[$key]);
    }
}
