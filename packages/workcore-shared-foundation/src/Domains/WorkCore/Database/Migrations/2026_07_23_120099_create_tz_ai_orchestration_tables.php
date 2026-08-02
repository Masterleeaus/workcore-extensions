<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('agent_public_id', 26)->nullable();
            $table->string('channel', 80)->default('internal');
            $table->string('title', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id','actor_user_id','status','last_message_at'], 'tz_ai_conversation_actor_idx');
            $table->index(['company_id','channel','status'], 'tz_ai_conversation_channel_idx');
        });

        Schema::create('tz_ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('tz_ai_conversations')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('role', 30);
            $table->longText('content');
            $table->string('name', 128)->nullable();
            $table->string('tool_call_id', 255)->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('token_count')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id','sequence'], 'tz_ai_message_sequence_uq');
            $table->index(['company_id','conversation_id','sequence'], 'tz_ai_message_company_sequence_idx');
            $table->index(['company_id','tool_call_id'], 'tz_ai_message_tool_call_idx');
        });

        Schema::create('tz_ai_orchestration_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('tz_ai_conversations')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('agent_public_id', 26)->nullable();
            $table->string('status', 30);
            $table->char('idempotency_key', 64);
            $table->string('source', 80)->default('workcore.native_ai');
            $table->string('channel', 80)->default('internal');
            $table->unsignedInteger('step_count')->default(0);
            $table->char('pending_approval_public_id', 26)->nullable();
            $table->json('request_snapshot')->nullable();
            $table->json('result_snapshot')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id','idempotency_key'], 'tz_ai_orchestration_idempotency_uq');
            $table->index(['company_id','conversation_id','status'], 'tz_ai_orchestration_conversation_idx');
            $table->index(['company_id','actor_user_id','started_at'], 'tz_ai_orchestration_actor_idx');
        });

        Schema::create('tz_ai_orchestration_steps', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('orchestration_run_id')->constrained('tz_ai_orchestration_runs')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('step_type', 40);
            $table->string('status', 30);
            $table->json('input_snapshot')->nullable();
            $table->json('output_snapshot')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['orchestration_run_id','sequence'], 'tz_ai_orchestration_step_sequence_uq');
            $table->index(['company_id','step_type','status'], 'tz_ai_orchestration_step_type_idx');
        });

        Schema::create('tz_ai_approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('orchestration_run_id')->constrained('tz_ai_orchestration_runs')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('tz_ai_conversations')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tool_call_id', 255);
            $table->string('tool_name', 128);
            $table->json('tool_input');
            $table->string('risk', 20);
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id','orchestration_run_id','tool_call_id'], 'tz_ai_approval_tool_call_uq');
            $table->index(['company_id','status','expires_at'], 'tz_ai_approval_status_idx');
        });

        Schema::create('tz_ai_memories', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('agent_public_id', 26)->nullable();
            $table->string('scope', 30);
            $table->string('memory_key', 160);
            $table->longText('memory_value');
            $table->decimal('confidence', 5, 4)->default(1);
            $table->string('source', 80)->default('explicit');
            $table->json('provenance')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id','owner_user_id','scope'], 'tz_ai_memory_owner_idx');
            $table->index(['company_id','agent_public_id','scope'], 'tz_ai_memory_agent_idx');
            $table->index(['company_id','expires_at'], 'tz_ai_memory_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_ai_memories');
        Schema::dropIfExists('tz_ai_approval_requests');
        Schema::dropIfExists('tz_ai_orchestration_steps');
        Schema::dropIfExists('tz_ai_orchestration_runs');
        Schema::dropIfExists('tz_ai_messages');
        Schema::dropIfExists('tz_ai_conversations');
    }
};
