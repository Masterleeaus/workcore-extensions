<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Providers;

use App\Domains\WorkCore\System\AI\Contracts\{AiRunRecorderContract,AiToolExecutorContract,AiToolRunRecorderContract,CredentialResolverContract,ModelGatewayContract,ModelProfileResolverContract,ToolCatalogueContract};
use App\Domains\WorkCore\System\AI\Orchestration\{AgentOrchestrator,ApprovalService,ContextWindowBuilder,DatabaseAgentProfileResolver,MemoryService,OrchestrationStateMachine,ToolApprovalPolicy,ToolCallNormalizer};
use App\Domains\WorkCore\System\AI\Orchestration\Contracts\{AgentProfileResolverContract,ApprovalStoreContract,ConversationStoreContract,MemoryStoreContract,OrchestrationRunStoreContract};
use App\Domains\WorkCore\System\AI\Orchestration\Persistence\{DatabaseApprovalStore,DatabaseConversationStore,DatabaseMemoryStore,DatabaseOrchestrationRunStore};
use App\Domains\WorkCore\System\AI\Persistence\{DatabaseAiRunRecorder,DatabaseAiToolRunRecorder,DatabaseModelProfileResolver};
use App\Domains\WorkCore\System\AI\Policies\{AiBudgetGuard,AiRateLimiter,ProviderCircuitBreaker};
use App\Domains\WorkCore\System\AI\Security\{NativeAiAccessGate,SensitiveDataRedactor};
use App\Domains\WorkCore\System\AI\Tools\{AiToolRegistry,AiToolRouter,JsonSchemaValidator,NativeToolCatalogue,WorkCoreActionToolExecutor,WorkCoreReadToolExecutor};
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkCoreAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../Config/workcore_ai.php', 'workcore_ai');
        if (! (bool) config('workcore_ai.enabled', true)) {
            return;
        }

        $this->app->singleton(SensitiveDataRedactor::class, static fn () => new SensitiveDataRedactor((array) config('workcore_ai.sensitive_keys', [])));
        $this->app->bind(CredentialResolverContract::class, (string) config('workcore_ai.credential_resolver'));
        $this->app->bind(ModelProfileResolverContract::class, DatabaseModelProfileResolver::class);
        $this->app->bind(AiRunRecorderContract::class, DatabaseAiRunRecorder::class);
        $this->app->bind(AiToolRunRecorderContract::class, DatabaseAiToolRunRecorder::class);
        $this->app->singleton(NativeAiAccessGate::class, fn ($app) => new NativeAiAccessGate(
            $app->make(\App\Domains\WorkCore\System\Contracts\TenantContextContract::class),
            $app->make(\App\Domains\WorkCore\System\Contracts\OperationContextContract::class),
            $app->make(EntitlementResolverContract::class),
            $app->make(PermissionResolverContract::class),
            (string) config('workcore_ai.access.capability', 'workcore.ai'),
            (string) config('workcore_ai.access.use_permission', 'workcore.ai.use'),
            (string) config('workcore_ai.access.tool_permission', 'workcore.ai.execute_tools'),
        ));

        $this->app->singleton(JsonSchemaValidator::class);
        $this->app->singleton(AiToolRegistry::class);
        $this->app->singleton(ModelProviderFactory::class);
        $this->app->singleton(AiRateLimiter::class);
        $this->app->singleton(AiBudgetGuard::class);
        $this->app->singleton(ProviderCircuitBreaker::class);
        $this->app->singleton(NativeToolCatalogue::class, fn ($app) => new NativeToolCatalogue(
            $app,
            $app->make(BusinessActionRegistry::class),
            $app->make(ReadModelRegistry::class),
            $app->make(EntitlementResolverContract::class),
            $app->make(PermissionResolverContract::class),
            $app->make(NativeAiAccessGate::class),
            $app->make(AiToolRegistry::class),
            array_values((array) config('workcore_ai.tool_registries', [])),
        ));
        $this->app->scoped(WorkCoreActionToolExecutor::class);
        $this->app->scoped(WorkCoreReadToolExecutor::class);
        $this->app->scoped(AiToolRouter::class);
        $this->app->scoped(NativeModelGateway::class);
        $this->app->alias(NativeModelGateway::class, ModelGatewayContract::class);
        $this->app->alias(AiToolRouter::class, AiToolExecutorContract::class);
        $this->app->alias(NativeToolCatalogue::class, ToolCatalogueContract::class);

        $this->app->bind(ConversationStoreContract::class, DatabaseConversationStore::class);
        $this->app->bind(OrchestrationRunStoreContract::class, DatabaseOrchestrationRunStore::class);
        $this->app->bind(ApprovalStoreContract::class, DatabaseApprovalStore::class);
        $this->app->bind(MemoryStoreContract::class, DatabaseMemoryStore::class);
        $this->app->bind(AgentProfileResolverContract::class, DatabaseAgentProfileResolver::class);
        $this->app->singleton(OrchestrationStateMachine::class);
        $this->app->singleton(ToolCallNormalizer::class);
        $this->app->singleton(ToolApprovalPolicy::class);
        $this->app->singleton(ContextWindowBuilder::class, static fn () => new ContextWindowBuilder(
            (int) config('workcore_ai.orchestration.max_history_messages', 40),
            (int) config('workcore_ai.orchestration.max_context_characters', 48000),
            (int) config('workcore_ai.orchestration.memory_limit', 20),
        ));
        $this->app->scoped(ApprovalService::class, fn ($app) => new ApprovalService(
            $app->make(NativeAiAccessGate::class),
            $app->make(PermissionResolverContract::class),
            $app->make(ApprovalStoreContract::class),
            (string) config('workcore_ai.orchestration.permissions.approve_actions', 'workcore.ai.approve_actions'),
        ));
        $this->app->scoped(MemoryService::class, fn ($app) => new MemoryService(
            $app->make(NativeAiAccessGate::class),
            $app->make(PermissionResolverContract::class),
            $app->make(MemoryStoreContract::class),
            $app->make(SensitiveDataRedactor::class),
            (string) config('workcore_ai.orchestration.permissions.manage_memory', 'workcore.ai.manage_memory'),
        ));
        $this->app->scoped(AgentOrchestrator::class, fn ($app) => new AgentOrchestrator(
            $app->make(NativeAiAccessGate::class),
            $app->make(AgentProfileResolverContract::class),
            $app->make(ConversationStoreContract::class),
            $app->make(OrchestrationRunStoreContract::class),
            $app->make(ApprovalStoreContract::class),
            $app->make(MemoryStoreContract::class),
            $app->make(ModelGatewayContract::class),
            $app->make(ToolCatalogueContract::class),
            $app->make(AiToolExecutorContract::class),
            $app->make(ContextWindowBuilder::class),
            $app->make(ToolCallNormalizer::class),
            $app->make(ToolApprovalPolicy::class),
            $app->make(OrchestrationStateMachine::class),
            (int) config('workcore_ai.orchestration.max_steps', 8),
            (int) config('workcore_ai.orchestration.max_tool_calls', 12),
            (int) config('workcore_ai.orchestration.approval_expiry_minutes', 30),
            (int) config('workcore_ai.orchestration.memory_limit', 20),
        ));
    }

    public function boot(): void
    {
        if (! (bool) config('workcore_ai.enabled', true)) {
            return;
        }
        $this->publishes([
            __DIR__ . '/../../../Config/workcore_ai.php' => config_path('workcore_ai.php'),
        ], 'workcore-ai-config');
        if ((bool) config('workcore_ai.orchestration.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
