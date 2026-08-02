<?php

declare(strict_types=1);

namespace App\Domains\WorkCore;

use App\Console\Commands\WorkCoreBootstrapCompanyCommand;
use App\Domains\WorkCore\System\Actions\{BusinessActionDispatcher, BusinessActionRegistry};
use App\Domains\WorkCore\System\Actions\Contracts\{
    ActionAuditRecorderContract,
    ActionIdempotencyStoreContract,
    ConfirmationVerifierContract,
    DomainEventRecorderContract,
    EntitlementResolverContract
};
use App\Domains\WorkCore\System\Actions\Infrastructure\{
    DatabaseActionAuditRecorder,
    DatabaseActionIdempotencyStore,
    DatabaseDomainEventRecorder
};
use App\Domains\WorkCore\System\Actions\Policies\ExplicitConfirmationVerifier;
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Middleware\WorkCoreApiExceptionMiddleware;
use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Capabilities\{CapabilityDefinition, CapabilityRegistry};
use App\Domains\WorkCore\System\Capabilities\Middleware\RequireWorkCoreCapability;
use App\Domains\WorkCore\System\Console\Commands\{InspectWorkCoreDonorsCommand, PublishWorkCoreOutboxCommand, WorkCoreDiagnoseCommand};
use App\Domains\WorkCore\System\Context\{CompanyOperatingContextResolver, ContextIdNormalizer, OperationContext};
use App\Domains\WorkCore\System\Contracts\{
    OperationContextContract,
    PermissionResolverContract,
    TenantContextContract,
    TenantResolverContract
};
use App\Domains\WorkCore\System\Contracts\Records\{CompanyAccessContract, CompanyMemberAccessContract};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\{CompanyAccessAdapter, CompanyMemberAccessAdapter};
use App\Domains\WorkCore\System\Donors\{DonorSourceRegistry, FeatureSourceRegistry};
use App\Domains\WorkCore\System\Host\Contracts\{MenuAdapterContract, NotificationAdapterContract, StorageAdapterContract, ToolBridgeContract};
use App\Domains\WorkCore\System\Host\{EventNotificationAdapter, LaravelPrivateStorageAdapter, NullMenuAdapter, WorkCoreToolBridge};
use App\Domains\WorkCore\System\Notifications\NotificationDispatcherContract;
use App\Domains\WorkCore\System\Outbox\{DatabaseOutboxPublisher, OutboxPublisherContract, OutboxTransportContract};
use App\Domains\WorkCore\System\ReadModels\{ReadModelExecutor, ReadModelRegistry};
use App\Domains\WorkCore\System\References\RecordTypeRegistry;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use App\Domains\WorkCore\System\Tenancy\{ResolveWorkCoreTenant, TenantContext};
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class WorkCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/workcore.php', 'workcore');
        foreach (['trade_compliance', 'vertical_codebooks', 'vertical_operations', 'vertical_setup', 'workcore_ai'] as $name) {
            $path = __DIR__ . "/Config/{$name}.php";
            if (is_file($path)) {
                $this->mergeConfigFrom($path, "workcore.{$name}");
            }
        }

        if (! (bool) config('workcore.enabled', true)) {
            return;
        }

        $helpers = __DIR__ . '/System/Support/helpers.php';
        if (is_file($helpers)) {
            require_once $helpers;
        }

        $this->app->scoped(TenantContextContract::class, static fn (): TenantContext => new TenantContext());
        $this->app->scoped(OperationContextContract::class, static fn (): OperationContext => new OperationContext());

        $this->bindConfigured(TenantResolverContract::class, 'workcore.tenant_resolver');
        $this->bindConfigured(PermissionResolverContract::class, 'workcore.permission_resolver');
        $this->bindConfigured(EntitlementResolverContract::class, 'workcore.entitlement_resolver');
        $this->bindConfigured(OutboxTransportContract::class, 'workcore.outbox.transport');

        $this->app->singleton(BusinessActionRegistry::class);
        $this->app->singleton(ReadModelRegistry::class);
        $this->app->singleton(ReadModelExecutor::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(WorkModuleRegistry::class);
        $this->app->singleton(DonorSourceRegistry::class);
        $this->app->singleton(FeatureSourceRegistry::class);
        $this->app->singleton(RecordTypeRegistry::class, static fn (): RecordTypeRegistry => new RecordTypeRegistry((array) config('workcore.references.types', [])));
        $this->app->scoped(BusinessActionDispatcher::class);

        $this->app->singleton(ContextIdNormalizer::class);
        $this->app->singleton(CompanyOperatingContextResolver::class);
        $this->app->singleton(CompanyScopedReferenceValidator::class);
        $this->app->singleton(CompanyRecordAuthorizer::class);
        $this->app->singleton(ApiResponseFactory::class);
        $this->app->singleton(\App\Domains\WorkCore\System\Queue\WorkCoreContextPayloadFactory::class);

        $this->app->bind(CompanyAccessContract::class, CompanyAccessAdapter::class);
        $this->app->bind(CompanyMemberAccessContract::class, CompanyMemberAccessAdapter::class);

        $this->app->bind(ConfirmationVerifierContract::class, ExplicitConfirmationVerifier::class);
        $this->app->bind(ActionIdempotencyStoreContract::class, DatabaseActionIdempotencyStore::class);
        $this->app->bind(ActionAuditRecorderContract::class, DatabaseActionAuditRecorder::class);
        $this->app->bind(DomainEventRecorderContract::class, DatabaseDomainEventRecorder::class);
        $this->app->bind(OutboxPublisherContract::class, DatabaseOutboxPublisher::class);

        $this->app->singleton(MenuAdapterContract::class, NullMenuAdapter::class);
        $this->app->singleton(NotificationAdapterContract::class, EventNotificationAdapter::class);
        $this->app->alias(NotificationAdapterContract::class, NotificationDispatcherContract::class);
        $this->app->singleton(StorageAdapterContract::class, LaravelPrivateStorageAdapter::class);
        $this->app->singleton(ToolBridgeContract::class, WorkCoreToolBridge::class);

        $this->registerCapabilities();
        $this->registerModules();
    }

    public function boot(Router $router): void
    {
        if (! (bool) config('workcore.enabled', true)) {
            return;
        }

        $router->aliasMiddleware((string) config('workcore.middleware_alias', 'workcore.tenant'), ResolveWorkCoreTenant::class);
        $router->aliasMiddleware((string) config('workcore.capability_middleware_alias', 'workcore.capability'), RequireWorkCoreCapability::class);
        $router->aliasMiddleware((string) config('workcore.api.middleware_alias', 'workcore.api'), WorkCoreApiExceptionMiddleware::class);

        $this->registerRateLimiters();

        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        if ((bool) config('workcore.api.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
        }

        if ((bool) config('workcore.tenancy.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
        }

        if (is_dir(__DIR__ . '/Resources/views')) {
            $this->loadViewsFrom(__DIR__ . '/Resources/views', 'workcore');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                WorkCoreDiagnoseCommand::class,
                PublishWorkCoreOutboxCommand::class,
                InspectWorkCoreDonorsCommand::class,
                WorkCoreBootstrapCompanyCommand::class,
            ]);
        }

        $publishes = [__DIR__ . '/Config/workcore.php' => config_path('workcore.php')];
        foreach (['trade_compliance', 'vertical_codebooks', 'vertical_operations', 'vertical_setup', 'workcore_ai'] as $name) {
            $path = __DIR__ . "/Config/{$name}.php";
            if (is_file($path)) {
                $publishes[$path] = config_path("workcore-{$name}.php");
            }
        }
        $this->publishes($publishes, 'workcore-config');
    }

    private function bindConfigured(string $contract, string $configKey): void
    {
        $implementation = config($configKey);
        if (! is_string($implementation) || $implementation === '' || ! class_exists($implementation)) {
            throw new RuntimeException("Invalid WorkCore binding [{$configKey}].");
        }

        $this->app->bind($contract, $implementation);
    }

    private function registerCapabilities(): void
    {
        $registry = $this->app->make(CapabilityRegistry::class);
        foreach ((array) config('workcore.capabilities', []) as $key => $definition) {
            $registry->register(new CapabilityDefinition(
                key: (string) $key,
                provider: (string) ($definition['provider'] ?? 'WorkCore'),
                version: (string) ($definition['version'] ?? '0.1.0'),
                requires: array_values((array) ($definition['requires'] ?? [])),
                metadata: (array) ($definition['metadata'] ?? []),
            ));
        }
    }

    private function registerModules(): void
    {
        $registry = $this->app->make(WorkModuleRegistry::class);
        foreach ((array) config('workcore.modules', []) as $key => $module) {
            if (($module['enabled'] ?? false) !== true) {
                continue;
            }

            $provider = $module['provider'] ?? null;
            if (! is_string($provider)) {
                throw new RuntimeException("Configured WorkCore module [{$key}] has no provider class.");
            }

            if (! class_exists($provider)) {
                continue;
            }

            $registry->define((string) $key, $provider);
        }

        foreach ([
            \App\Domains\WorkCore\Providers\BusinessNetworkServiceProvider::class,
            \App\Domains\WorkCore\Providers\CommercialServiceProvider::class,
            \App\Domains\WorkCore\Providers\WorkOperationsServiceProvider::class,
            \App\Domains\WorkCore\Providers\PropertyOperationsServiceProvider::class,
            \App\Domains\WorkCore\Providers\WorkforceAssuranceServiceProvider::class,
        ] as $provider) {
            if (! class_exists($provider)) {
                continue;
            }

            $this->app->register($provider);
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('workcore-actions', static function (Request $request): Limit {
            $companyHeader = (string) config('workcore.tenancy.header', 'X-Titan-Company');
            $activeCompanyColumn = (string) config('workcore.identity.active_company_column', 'active_company_id');
            $company = (string) ($request->header($companyHeader)
                ?: $request->user()?->getAttribute($activeCompanyColumn)
                ?: 'guest');
            $actor = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return Limit::perMinute((int) config('workcore.api.action_rate_limit_per_minute', 60))
                ->by($company . ':' . $actor);
        });

        RateLimiter::for('workcore-offline-sync', static function (Request $request): Limit {
            $companyHeader = (string) config('workcore.tenancy.header', 'X-Titan-Company');
            $activeCompanyColumn = (string) config('workcore.identity.active_company_column', 'active_company_id');
            $company = (string) ($request->header($companyHeader)
                ?: $request->user()?->getAttribute($activeCompanyColumn)
                ?: 'guest');
            $device = (string) ($request->input('device_id') ?: $request->ip());

            return Limit::perMinute((int) config('workcore.api.sync_rate_limit_per_minute', 120))
                ->by($company . ':' . $device);
        });
    }
}
