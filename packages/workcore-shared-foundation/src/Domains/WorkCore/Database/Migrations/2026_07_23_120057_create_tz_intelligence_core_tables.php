<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_ai_observations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique('ai_obs_public_uq');
            $table->unsignedBigInteger('company_id');
            $table->string('domain_key', 100);
            $table->string('observation_type', 100);
            $table->text('summary');
            $table->decimal('confidence', 5, 4);
            $table->json('evidence_json')->nullable();
            $table->json('data_gaps_json')->nullable();
            $table->string('status', 40)->default('observed');
            $table->unsignedBigInteger('observed_by_user_id')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'domain_key', 'status'], 'ai_obs_company_domain_idx');
        });

        Schema::create('tz_ai_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique('ai_rec_public_uq');
            $table->unsignedBigInteger('company_id');
            $table->uuid('observation_public_id')->nullable();
            $table->text('summary');
            $table->string('proposed_action', 255);
            $table->decimal('confidence', 5, 4);
            $table->string('risk', 20)->default('medium');
            $table->string('status', 40)->default('review_required');
            $table->json('success_metrics_json')->nullable();
            $table->unsignedBigInteger('proposed_by_user_id')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'risk'], 'ai_rec_company_status_idx');
        });

        Schema::create('tz_ai_evidence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('stream_key', 160);
            $table->unsignedBigInteger('sequence');
            $table->uuid('event_id');
            $table->string('event_type', 160);
            $table->char('content_hash', 64);
            $table->string('previous_hash', 64);
            $table->char('signature', 64);
            $table->string('key_id', 80)->default('primary');
            $table->json('content_json');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'stream_key', 'sequence'], 'ai_evidence_stream_seq_uq');
            $table->unique(['company_id', 'event_id'], 'ai_evidence_event_uq');
        });

        Schema::create('tz_ai_confirmation_nonces', function (Blueprint $table): void {
            $table->id();
            $table->char('nonce_hash', 64)->unique('ai_nonce_hash_uq');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action_key', 180);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'actor_id', 'expires_at'], 'ai_nonce_scope_expiry_idx');
        });

        Schema::create('tz_ai_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique('ai_run_public_uq');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('task_type', 120);
            $table->string('status', 40);
            $table->string('correlation_id', 100)->nullable();
            $table->string('failure_category', 80)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->unsignedInteger('elapsed_ms')->default(0);
            $table->json('result_json')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'task_type', 'status'], 'ai_run_company_task_idx');
        });

        Schema::create('tz_ai_capability_signals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('requirement_hash', 64);
            $table->string('capability_key', 160)->nullable();
            $table->string('decision', 20);
            $table->decimal('match_score', 5, 4)->default(0);
            $table->string('outcome', 60)->nullable();
            $table->timestamp('last_observed_at');
            $table->unsignedInteger('recurrence_count')->default(1);
            $table->json('evidence_json')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'requirement_hash'], 'ai_signal_company_req_uq');
            $table->index(['capability_key', 'decision', 'outcome'], 'ai_signal_capability_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_ai_capability_signals');
        Schema::dropIfExists('tz_ai_task_runs');
        Schema::dropIfExists('tz_ai_confirmation_nonces');
        Schema::dropIfExists('tz_ai_evidence');
        Schema::dropIfExists('tz_ai_recommendations');
        Schema::dropIfExists('tz_ai_observations');
    }
};
