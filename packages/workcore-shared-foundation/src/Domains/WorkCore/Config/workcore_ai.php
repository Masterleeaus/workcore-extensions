<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\AI\Security\NullCredentialResolver;
use App\Domains\WorkCore\System\Modules\Assets\AI\AssetsToolRegistry;
use App\Domains\WorkCore\System\Modules\Attendance\AI\AttendanceToolRegistry;
use App\Domains\WorkCore\System\Modules\AttendanceVerification\AI\AttendanceVerificationToolRegistry;
use App\Domains\WorkCore\System\Modules\CRM\AI\CRMToolRegistry;
use App\Domains\WorkCore\System\Modules\Catalogue\AI\CatalogueToolRegistry;
use App\Domains\WorkCore\System\Modules\Compliance\AI\ComplianceToolRegistry;
use App\Domains\WorkCore\System\Modules\Credentials\AI\CredentialsToolRegistry;
use App\Domains\WorkCore\System\Modules\Dispatch\AI\DispatchToolRegistry;
use App\Domains\WorkCore\System\Modules\Feedback\AI\FeedbackToolRegistry;
use App\Domains\WorkCore\System\Modules\Finance\AI\FinanceToolRegistry;
use App\Domains\WorkCore\System\Modules\Fleet\AI\FleetToolRegistry;
use App\Domains\WorkCore\System\Modules\Forms\AI\FormsToolRegistry;
use App\Domains\WorkCore\System\Modules\Inventory\AI\InventoryToolRegistry;
use App\Domains\WorkCore\System\Modules\Knowledge\AI\KnowledgeToolRegistry;
use App\Domains\WorkCore\System\Modules\Operations\AI\OperationsToolRegistry;
use App\Domains\WorkCore\System\Modules\Payroll\AI\PayrollToolRegistry;
use App\Domains\WorkCore\System\Modules\People\AI\PeopleToolRegistry;
use App\Domains\WorkCore\System\Modules\Premises\AI\PremisesToolRegistry;
use App\Domains\WorkCore\System\Modules\QRCode\AI\QRCodeToolRegistry;
use App\Domains\WorkCore\System\Modules\RecurringServices\AI\RecurringServicesToolRegistry;
use App\Domains\WorkCore\System\Modules\Repairs\AI\RepairsToolRegistry;
use App\Domains\WorkCore\System\Modules\Reviews\AI\ReviewsToolRegistry;
use App\Domains\WorkCore\System\Modules\Rosters\AI\RostersToolRegistry;
use App\Domains\WorkCore\System\Modules\Scheduling\AI\SchedulingToolRegistry;
use App\Domains\WorkCore\System\Modules\Supply\AI\SupplyToolRegistry;
use App\Domains\WorkCore\System\Modules\Support\AI\SupportToolRegistry;
use App\Domains\WorkCore\System\Modules\Territories\AI\TerritoriesToolRegistry;
use App\Domains\WorkCore\System\Modules\TitanVault\AI\TitanVaultToolRegistry;
use App\Domains\WorkCore\System\Modules\Wizards\AI\WizardsToolRegistry;
use App\Domains\WorkCore\System\Modules\Workforce\AI\WorkforceToolRegistry;

return [
    'enabled' => env('WORKCORE_NATIVE_AI_ENABLED', false),
    'credential_resolver' => NullCredentialResolver::class,
    'access' => [
        'capability' => 'workcore.ai',
        'use_permission' => 'workcore.ai.use',
        'tool_permission' => 'workcore.ai.execute_tools',
    ],
    'fallback_profiles' => [
        [
            'enabled' => env('WORKCORE_AI_OPENAI_ENABLED', false),
            'provider' => 'openai',
            'name' => 'OpenAI',
            'base_url' => env('WORKCORE_AI_OPENAI_BASE_URL', 'https://api.openai.com'),
            'secret_reference' => 'env:OPENAI_API_KEY',
            'model' => env('WORKCORE_AI_OPENAI_MODEL', 'gpt-4.1-mini'),
            'purpose' => '*',
            'priority' => 100,
        ],
        [
            'enabled' => env('WORKCORE_AI_LOCAL_ENABLED', false),
            'provider' => 'local',
            'name' => 'Local OpenAI-compatible model',
            'base_url' => env('WORKCORE_AI_LOCAL_BASE_URL', 'http://127.0.0.1:11434'),
            'secret_reference' => null,
            'model' => env('WORKCORE_AI_LOCAL_MODEL', 'llama3.2'),
            'purpose' => '*',
            'priority' => 200,
        ],
    ],
    'rate_limits' => [
        'requests_per_minute' => (int) env('WORKCORE_AI_REQUESTS_PER_MINUTE', 60),
    ],
    'budgets' => [
        'max_output_tokens_per_request' => (int) env('WORKCORE_AI_MAX_OUTPUT_TOKENS', 4096),
        'daily_company_tokens' => (int) env('WORKCORE_AI_DAILY_COMPANY_TOKENS', 0),
    ],
    'circuit_breaker' => [
        'failure_threshold' => (int) env('WORKCORE_AI_BREAKER_FAILURES', 3),
        'open_seconds' => (int) env('WORKCORE_AI_BREAKER_OPEN_SECONDS', 60),
    ],
    'orchestration' => [
        'enabled' => env('WORKCORE_AI_ORCHESTRATION_ENABLED', false),
        'routes_enabled' => env('WORKCORE_AI_ROUTES_ENABLED', false),
        'route_prefix' => env('WORKCORE_AI_ROUTE_PREFIX', 'api/v1/workcore/ai'),
        'middleware' => ['api', 'auth', 'workcore.tenant', 'workcore.api'],
        'max_steps' => (int) env('WORKCORE_AI_MAX_STEPS', 8),
        'max_tool_calls' => (int) env('WORKCORE_AI_MAX_TOOL_CALLS', 12),
        'max_history_messages' => (int) env('WORKCORE_AI_MAX_HISTORY_MESSAGES', 40),
        'max_context_characters' => (int) env('WORKCORE_AI_MAX_CONTEXT_CHARACTERS', 48000),
        'memory_limit' => (int) env('WORKCORE_AI_MEMORY_LIMIT', 20),
        'approval_expiry_minutes' => (int) env('WORKCORE_AI_APPROVAL_EXPIRY_MINUTES', 30),
        'default_agent' => [
            'key' => 'titan-zero',
            'name' => 'Titan Zero',
            'system_prompt' => 'You are Titan Zero, the native WorkCore business assistant. Use only the tools exposed for the active company and actor. Never claim an action occurred unless a governed tool result confirms it. Ask for clarification when required inputs are missing.',
            'tool_allowlist' => ['*'],
            'guardrails' => ['critical_tools' => 'blocked', 'high_risk_tools' => 'approval_required'],
            'settings' => ['temperature' => 0.2],
        ],
        'memory' => [
            'auto_extract' => false,
            'allow_company_scope' => true,
        ],
        'permissions' => [
            'view_conversations' => 'workcore.ai.view_conversations',
            'approve_actions' => 'workcore.ai.approve_actions',
            'manage_memory' => 'workcore.ai.manage_memory',
            'manage_company_memory' => 'workcore.ai.manage_company_memory',
        ],
    ],
    'sensitive_keys' => [
        'api_key','apikey','authorization','password','secret','token','access_token','refresh_token',
        'credential','private_key','client_secret','bank_account','card_number','cvv',
    ],
    'tool_registries' => [
        CRMToolRegistry::class, CatalogueToolRegistry::class, DispatchToolRegistry::class,
        KnowledgeToolRegistry::class, SupportToolRegistry::class, RostersToolRegistry::class,
        WorkforceToolRegistry::class, TitanVaultToolRegistry::class, WizardsToolRegistry::class,
        InventoryToolRegistry::class, AssetsToolRegistry::class, ComplianceToolRegistry::class,
        AttendanceToolRegistry::class, FormsToolRegistry::class,
        AttendanceVerificationToolRegistry::class, CredentialsToolRegistry::class,
        FeedbackToolRegistry::class, FinanceToolRegistry::class, FleetToolRegistry::class,
        OperationsToolRegistry::class, PayrollToolRegistry::class, PeopleToolRegistry::class,
        PremisesToolRegistry::class, QRCodeToolRegistry::class, RecurringServicesToolRegistry::class,
        RepairsToolRegistry::class, ReviewsToolRegistry::class, SchedulingToolRegistry::class,
        SupplyToolRegistry::class, TerritoriesToolRegistry::class,
    ],
];
