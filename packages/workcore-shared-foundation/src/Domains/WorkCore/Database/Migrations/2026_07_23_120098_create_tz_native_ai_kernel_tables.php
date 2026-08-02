<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_ai_provider_profiles', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('provider_key', 80);
            $table->string('name', 160);
            $table->string('base_url', 500)->nullable();
            $table->string('secret_reference', 255)->nullable()->comment('Reference only; never store provider secrets here.');
            $table->json('settings')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id','provider_key'], 'tz_ai_provider_company_key_uq');
            $table->index(['company_id','is_active','priority'], 'tz_ai_provider_active_idx');
        });

        Schema::create('tz_ai_model_profiles', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->constrained('tz_ai_provider_profiles')->cascadeOnDelete();
            $table->string('purpose', 80)->default('*');
            $table->string('model_key', 160);
            $table->string('display_name', 160)->nullable();
            $table->unsignedInteger('max_context_tokens')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->decimal('input_cost_per_million', 20, 8)->nullable();
            $table->decimal('output_cost_per_million', 20, 8)->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id','provider_profile_id','purpose','model_key'], 'tz_ai_model_company_provider_purpose_uq');
            $table->index(['company_id','purpose','is_active','priority'], 'tz_ai_model_resolve_idx');
        });

        Schema::create('tz_ai_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('policy_key', 120);
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('draft');
            $table->json('policy');
            $table->char('checksum', 64);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id','policy_key','version'], 'tz_ai_policy_company_key_version_uq');
            $table->index(['company_id','policy_key','status'], 'tz_ai_policy_active_idx');
        });

        Schema::create('tz_ai_agents', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('agent_key', 120);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('default_model_profile_id')->nullable()->constrained('tz_ai_model_profiles')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id','agent_key'], 'tz_ai_agent_company_key_uq');
            $table->index(['company_id','status'], 'tz_ai_agent_status_idx');
        });

        Schema::create('tz_ai_agent_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('tz_ai_agents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('draft');
            $table->longText('system_prompt');
            $table->json('tool_allowlist')->nullable();
            $table->json('guardrails')->nullable();
            $table->foreignId('model_profile_id')->nullable()->constrained('tz_ai_model_profiles')->nullOnDelete();
            $table->foreignId('policy_version_id')->nullable()->constrained('tz_ai_policy_versions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['agent_id','version'], 'tz_ai_agent_version_uq');
            $table->index(['company_id','agent_id','status'], 'tz_ai_agent_version_status_idx');
        });

        Schema::create('tz_ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('agent_public_id', 26)->nullable();
            $table->char('agent_version_public_id', 26)->nullable();
            $table->char('provider_profile_public_id', 26)->nullable();
            $table->char('model_profile_public_id', 26)->nullable();
            $table->string('purpose', 80);
            $table->string('status', 30);
            $table->string('source', 80)->default('workcore.native_ai');
            $table->char('thread_public_id', 26)->nullable();
            $table->char('correlation_id', 26)->nullable();
            $table->char('request_hash', 64);
            $table->json('request_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->string('provider_key', 80)->nullable();
            $table->string('model_key', 160)->nullable();
            $table->string('provider_response_id', 255)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 20, 8)->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','status','started_at'], 'tz_ai_run_company_status_idx');
            $table->index(['company_id','purpose','started_at'], 'tz_ai_run_company_purpose_idx');
            $table->index(['company_id','actor_user_id','started_at'], 'tz_ai_run_actor_idx');
        });

        Schema::create('tz_ai_tool_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('tz_ai_runs')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tool_name', 128);
            $table->string('tool_kind', 30);
            $table->string('target_key', 160);
            $table->string('status', 30);
            $table->string('risk', 20);
            $table->string('confirmation_id', 160)->nullable();
            $table->char('idempotency_key', 64)->nullable();
            $table->json('input_snapshot')->nullable();
            $table->json('output_snapshot')->nullable();
            $table->string('action_audit_id', 64)->nullable();
            $table->json('event_ids')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id','tool_name','started_at'], 'tz_ai_tool_company_name_idx');
            $table->index(['company_id','status','started_at'], 'tz_ai_tool_company_status_idx');
        });

        Schema::create('tz_ai_usage_ledger', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('tz_ai_runs')->nullOnDelete();
            $table->foreignId('ai_tool_run_id')->nullable()->constrained('tz_ai_tool_runs')->nullOnDelete();
            $table->string('provider_key', 80);
            $table->string('model_key', 160);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 20, 8)->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id','occurred_at'], 'tz_ai_usage_company_date_idx');
            $table->index(['company_id','provider_key','model_key'], 'tz_ai_usage_provider_model_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_ai_usage_ledger');
        Schema::dropIfExists('tz_ai_tool_runs');
        Schema::dropIfExists('tz_ai_runs');
        Schema::dropIfExists('tz_ai_agent_versions');
        Schema::dropIfExists('tz_ai_agents');
        Schema::dropIfExists('tz_ai_policy_versions');
        Schema::dropIfExists('tz_ai_model_profiles');
        Schema::dropIfExists('tz_ai_provider_profiles');
    }
};
