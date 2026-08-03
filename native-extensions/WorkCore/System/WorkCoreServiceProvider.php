<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System;

use App\Domains\Marketplace\Contracts\ExtensionRegisterKeyProviderInterface;
use App\Domains\Marketplace\Contracts\UninstallExtensionServiceProviderInterface;
use App\Domains\WorkCore\System\Actions\Contracts\ConfirmationVerifierContract;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementRevisionResolverContract;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\Entitlements\CompanyEntitlementRefreshService;
use App\Domains\WorkCore\System\Entitlements\Contracts\EffectiveSubscriptionResolverContract;
use App\Domains\WorkCore\System\Entitlements\ProjectedCompanyEntitlementResolver;
use App\Domains\WorkCore\System\Entitlements\WorkCorePlanEntitlementAdapter;
use App\Domains\WorkCore\System\Intelligence\Approvals\BoundConfirmationVerifier;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationGrantService;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationGrantSigner;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationNonceStoreContract;
use App\Domains\WorkCore\System\Intelligence\Approvals\DatabaseConfirmationNonceStore;
use App\Extensions\WorkCore\System\Console\Commands\RefreshWorkCoreEntitlementsCommand;
use App\Extensions\WorkCore\System\Entitlements\MagicAIEffectiveSubscriptionResolver;
use App\Extensions\WorkCore\System\Runtime\WorkCoreHostAliasRegistrar;
use App\Extensions\WorkCore\System\Runtime\WorkCoreRuntimeAutoloader;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class WorkCoreServiceProvider extends ServiceProvider implements
    ExtensionRegisterKeyProviderInterface,
    UninstallExtensionServiceProviderInterface
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workcore-native.php', 'workcore-native');

        if (! (bool) config('workcore-native.enabled', true)) {
            return;
        }

        WorkCoreRuntimeAutoloader::register(__DIR__ . '/../Runtime');
        WorkCoreHostAliasRegistrar::register();

        $requiredRuntimeClasses = [
            \App\Domains\WorkCore\WorkCoreServiceProvider::class,
            BoundConfirmationVerifier::class,
            ConfirmationGrantService::class,
            ConfirmationGrantSigner::class,
            ConfirmationNonceStoreContract::class,
            DatabaseConfirmationNonceStore::class,
            ProjectedCompanyEntitlementResolver::class,
            WorkCorePlanEntitlementAdapter::class,
            CompanyEntitlementRefreshService::class,
            EffectiveSubscriptionResolverContract::class,
            EntitlementRevisionResolverContract::class,
        ];
        foreach ($requiredRuntimeClasses as $requiredRuntimeClass) {
            if (! class_exists($requiredRuntimeClass) && ! interface_exists($requiredRuntimeClass)) {
                throw new RuntimeException("The packaged WorkCore runtime is missing [{$requiredRuntimeClass}].");
            }
        }

        $this->app->register(\App\Domains\WorkCore\WorkCoreServiceProvider::class);

        $middleware = config('workcore-native.api_middleware', [
            'api',
            'auth:api',
            'workcore.tenant',
            'workcore.api',
        ]);
        if (! is_array($middleware) || $middleware === []) {
            throw new RuntimeException('WorkCore native API middleware must be a non-empty array.');
        }
        $this->app['config']->set('workcore.api.middleware', array_values($middleware));

        $this->registerNativeConfirmationSecurity();
        $this->registerNativeEntitlements();
    }

    public function boot(): void
    {
        if (! (bool) config('workcore-native.enabled', true)) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RefreshWorkCoreEntitlementsCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../config/workcore-native.php' => config_path('workcore-native.php'),
        ], 'extension');
    }

    public function registerKey(): string
    {
        return 'workcore';
    }

    public static function uninstall(): void
    {
        // Intentionally retain WorkCore tenant data, evidence and audit history.
        // Repeated execution is a safe no-op; destructive purge is a separate operation.
    }

    private function registerNativeConfirmationSecurity(): void
    {
        $configuredKey = config('workcore-native.approvals.signing_key');
        $signingKey = is_string($configuredKey) && trim($configuredKey) !== ''
            ? $configuredKey
            : (string) config('app.key', '');
        if (strlen($signingKey) < 32) {
            throw new RuntimeException('WorkCore confirmation signing requires APP_KEY or WORKCORE_CONFIRMATION_SIGNING_KEY with at least 32 bytes.');
        }

        $keyId = (string) config('workcore-native.approvals.key_id', 'primary');
        $ttlSeconds = (int) config('workcore-native.approvals.ttl_seconds', 300);

        $this->app['config']->set('workcore.intelligence.approvals.enabled', true);
        $this->app['config']->set('workcore.intelligence.approvals.signing_key', $signingKey);
        $this->app['config']->set('workcore.intelligence.approvals.key_id', $keyId);
        $this->app['config']->set('workcore.intelligence.approvals.ttl_seconds', $ttlSeconds);
        $this->app['config']->set('workcore.intelligence.approvals.enforce_all', true);
        $this->app['config']->set('workcore.intelligence.approvals.allow_legacy_human_confirmation', false);

        $this->app->singleton(ConfirmationGrantSigner::class, static fn (): ConfirmationGrantSigner => new ConfirmationGrantSigner(
            $signingKey,
            $keyId,
        ));
        $this->app->bind(ConfirmationNonceStoreContract::class, DatabaseConfirmationNonceStore::class);
        $this->app->singleton(ConfirmationGrantService::class, static fn ($app): ConfirmationGrantService => new ConfirmationGrantService(
            $app->make(ConfirmationGrantSigner::class),
            $app->make(ConfirmationNonceStoreContract::class),
            $ttlSeconds,
        ));
        $this->app->bind(ConfirmationVerifierContract::class, static fn ($app): BoundConfirmationVerifier => new BoundConfirmationVerifier(
            $app->make(ConfirmationGrantSigner::class),
            $app->make(ConfirmationNonceStoreContract::class),
            [],
            true,
            false,
        ));
    }

    private function registerNativeEntitlements(): void
    {
        $featureMap = config('workcore-native.entitlements.feature_map', []);
        $bootstrapCapabilities = config('workcore-native.entitlements.bootstrap_capabilities', []);
        $subscriptionSource = config('workcore-native.entitlements.subscription_source', []);
        if (! is_array($featureMap) || ! is_array($bootstrapCapabilities) || ! is_array($subscriptionSource)) {
            throw new RuntimeException('WorkCore native entitlement configuration is invalid.');
        }

        $this->app->singleton(ProjectedCompanyEntitlementResolver::class, static fn ($app): ProjectedCompanyEntitlementResolver => new ProjectedCompanyEntitlementResolver(
            $app->make(ConnectionInterface::class),
            $app->make(CapabilityRegistry::class),
            array_values(array_filter($bootstrapCapabilities, 'is_string')),
        ));
        $this->app->bind(EntitlementResolverContract::class, static fn ($app): ProjectedCompanyEntitlementResolver => $app->make(ProjectedCompanyEntitlementResolver::class));
        $this->app->bind(EntitlementRevisionResolverContract::class, static fn ($app): ProjectedCompanyEntitlementResolver => $app->make(ProjectedCompanyEntitlementResolver::class));
        $this->app->singleton(WorkCorePlanEntitlementAdapter::class, static fn ($app): WorkCorePlanEntitlementAdapter => new WorkCorePlanEntitlementAdapter(
            $app->make(ConnectionInterface::class),
            $app->make(CapabilityRegistry::class),
            $featureMap,
        ));
        $this->app->singleton(MagicAIEffectiveSubscriptionResolver::class, static fn ($app): MagicAIEffectiveSubscriptionResolver => new MagicAIEffectiveSubscriptionResolver(
            $app->make(ConnectionInterface::class),
            $subscriptionSource,
        ));
        $this->app->bind(EffectiveSubscriptionResolverContract::class, static fn ($app): MagicAIEffectiveSubscriptionResolver => $app->make(MagicAIEffectiveSubscriptionResolver::class));
        $this->app->singleton(CompanyEntitlementRefreshService::class);
    }
}
